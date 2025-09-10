<?php

namespace App\Repository;

use App\Entity\Couple;
use App\Enumeration\StatutCouple;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Couple>
 */
class CoupleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Couple::class);
    }

    /**
     * Récupère tous les couples actifs
     *
     * @return Couple[]
     */
    public function findActiveCouples(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', StatutCouple::ACTIF)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un couple par son domaine personnalisé ou sous-domaine
     *
     * @param string $domain
     * @return Couple|null
     */
    public function findByDomain(string $domain): ?Couple
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.domainePersonnalise = :domain OR c.sousDomaine = :domain')
            ->andWhere('c.statut = :statut')
            ->setParameter('domain', $domain)
            ->setParameter('statut', StatutCouple::ACTIF)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un couple par son domaine personnalisé uniquement
     *
     * @param string $customDomain
     * @return Couple|null
     */
    public function findByCustomDomain(string $customDomain): ?Couple
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.domainePersonnalise = :domain')
            ->andWhere('c.statut = :statut')
            ->setParameter('domain', $customDomain)
            ->setParameter('statut', StatutCouple::ACTIF)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un couple par son sous-domaine uniquement
     *
     * @param string $subdomain
     * @return Couple|null
     */
    public function findBySubdomain(string $subdomain): ?Couple
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.sousDomaine = :subdomain')
            ->andWhere('c.statut = :statut')
            ->setParameter('subdomain', $subdomain)
            ->setParameter('statut', StatutCouple::ACTIF)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les couples qui ont certains modules activés (optimisé pour PostgreSQL JSONB)
     *
     * @param array $modules Liste des modules à rechercher
     * @param bool $requireAll Si true, le couple doit avoir TOUS les modules, sinon au moins un
     * @return Couple[]
     */
    public function findWithModules(array $modules, bool $requireAll = false): array
    {
        if (empty($modules)) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.modulesActifs IS NOT NULL')
            ->setParameter('statut', StatutCouple::ACTIF);

        if ($requireAll) {
            // Le couple doit avoir TOUS les modules
            foreach ($modules as $index => $module) {
                $qb->andWhere("JSON_CONTAINS(c.modulesActifs, :module{$index}) = 1")
                    ->setParameter("module{$index}", json_encode($module));
            }
        } else {
            // Le couple doit avoir AU MOINS UN des modules (PostgreSQL JSONB)
            $orConditions = [];
            foreach ($modules as $index => $module) {
                $orConditions[] = "c.modulesActifs @> :module{$index}";
                $qb->setParameter("module{$index}", json_encode([$module]));
            }
            $qb->andWhere(implode(' OR ', $orConditions));
        }

        return $qb->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un couple a un module spécifique activé
     *
     * @param Uuid $coupleId
     * @param string $module
     * @return bool
     */
    public function hasModule(Uuid $coupleId, string $module): bool
    {
        $result = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.id = :coupleId')
            ->andWhere('c.modulesActifs @> :module')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('module', json_encode([$module]))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /**
     * Recherche par nom des mariés (insensible à la casse)
     *
     * @param string $name
     * @param bool $activeOnly
     * @return Couple[]
     */
    public function searchByName(string $name, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('(LOWER(c.nomMarie) LIKE :name OR LOWER(c.prenomMarie) LIKE :name OR LOWER(c.nomMarieConjoint) LIKE :name OR LOWER(c.prenomMarieConjoint) LIKE :name)')
            ->setParameter('name', '%' . strtolower($name) . '%')
            ->orderBy('c.dateCreation', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', StatutCouple::ACTIF);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche par email administrateur
     *
     * @param string $email
     * @return Couple|null
     */
    public function findByEmail(string $email): ?Couple
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.emailAdmin = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les couples par date de mariage (pour notifications, statistiques...)
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @param bool $activeOnly
     * @return Couple[]
     */
    public function findByWeddingDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.dateMariage BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.dateMariage', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', StatutCouple::ACTIF);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les couples qui se marient aujourd'hui
     *
     * @return Couple[]
     */
    public function findMarryingToday(): array
    {
        $today = new \DateTime('today');
        return $this->findByWeddingDateRange($today, $today);
    }

    /**
     * Trouve les couples qui se marient dans les X prochains jours
     *
     * @param int $days
     * @return Couple[]
     */
    public function findMarryingInNextDays(int $days): array
    {
        $today = new \DateTime('today');
        $futureDate = (clone $today)->modify("+{$days} days");

        return $this->findByWeddingDateRange($today, $futureDate);
    }

    /**
     * Compte le nombre de couples par statut
     *
     * @return array{actif: int, inactif: int, suspendu: int, total: int}
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.statut', 'COUNT(c.id) as count')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $stats = [
            'actif' => 0,
            'inactif' => 0,
            'suspendu' => 0,
            'total' => 0
        ];

        foreach ($result as $row) {
            $count = (int) $row['count'];
            $stats['total'] += $count;

            match ($row['statut']) {
                StatutCouple::ACTIF => $stats['actif'] = $count,
                StatutCouple::ARCHIVE => $stats['archive'] = $count,
                StatutCouple::SUSPENDU => $stats['suspendu'] = $count,
                default => null
            };
        }

        return $stats;
    }

    /**
     * Trouve les couples avec pagination
     *
     * @param int $page
     * @param int $limit
     * @param StatutCouple|null $statut
     * @param array $filters Filtres additionnels
     * @return array{data: Couple[], total: int, pages: int}
     */
    public function findPaginated(int $page = 1, int $limit = 20, ?StatutCouple $statut = null, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($statut !== null) {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', $statut);
        }

        // Filtres additionnels
        if (!empty($filters['search'])) {
            $qb->andWhere('(LOWER(c.nomMarie) LIKE :search OR LOWER(c.prenomMarie) LIKE :search OR LOWER(c.nomMarieConjoint) LIKE :search OR LOWER(c.prenomMarieConjoint) LIKE :search OR c.emailAdmin LIKE :search)')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('c.dateMariage >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('c.dateMariage <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        if (!empty($filters['modules'])) {
            foreach ($filters['modules'] as $index => $module) {
                $qb->andWhere("c.modulesActifs @> :module{$index}")
                    ->setParameter("module{$index}", json_encode([$module]));
            }
        }

        // Compte total
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Résultats paginés
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
            'pages' => (int) ceil($total / $limit)
        ];
    }

    /**
     * Trouve les couples créés récemment
     *
     * @param int $days Nombre de jours
     * @param int $limit
     * @return Couple[]
     */
    public function findRecentlyCreated(int $days = 7, int $limit = 10): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.dateCreation >= :since')
            ->andWhere('c.statut = :statut')
            ->setParameter('since', $since)
            ->setParameter('statut', StatutCouple::ACTIF)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les couples avec des domaines personnalisés
     *
     * @param bool $activeOnly
     * @return Couple[]
     */
    public function findWithCustomDomains(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.domainePersonnalise IS NOT NULL')
            ->orderBy('c.dateCreation', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', StatutCouple::ACTIF);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des modules les plus utilisés
     *
     * @param int $limit
     * @return array
     */
    public function getModuleStats(int $limit = 10): array
    {
        // Cette requête utilise les capacités JSONB de PostgreSQL
        $sql = "
            SELECT module, COUNT(*) as usage_count
            FROM couples c,
            LATERAL jsonb_array_elements_text(c.modules_actifs) AS module
            WHERE c.statut = :statut
            GROUP BY module
            ORDER BY usage_count DESC
            LIMIT :limit
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery([
            'statut' => StatutCouple::ACTIF->value,
            'limit' => $limit
        ])->fetchAllAssociative();
    }

    /**
     * Vérifie la disponibilité d'un sous-domaine
     *
     * @param string $subdomain
     * @param Uuid|null $excludeCoupleId
     * @return bool
     */
    public function isSubdomainAvailable(string $subdomain, ?Uuid $excludeCoupleId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.sousDomaine = :subdomain')
            ->setParameter('subdomain', $subdomain);

        if ($excludeCoupleId !== null) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeCoupleId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Récupère un couple par ID ou lance une exception si inexistant
     *
     * @param Uuid $id
     * @return Couple
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function findByIdOrFail(Uuid $id): Couple
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult(); // Lance une exception si pas trouvé
    }

    /**
     * Vérifie la disponibilité d'un domaine personnalisé
     *
     * @param string $domain
     * @param Uuid|null $excludeCoupleId
     * @return bool
     */
    public function isCustomDomainAvailable(string $domain, ?Uuid $excludeCoupleId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.domainePersonnalise = :domain')
            ->setParameter('domain', $domain);

        if ($excludeCoupleId !== null) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeCoupleId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Sauvegarde une entité Couple
     *
     * @param Couple $entity
     * @param bool $flush
     */
    public function save(Couple $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité Couple
     *
     * @param Couple $entity
     * @param bool $flush
     */
    public function remove(Couple $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

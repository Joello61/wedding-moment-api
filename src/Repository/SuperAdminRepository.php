<?php

namespace App\Repository;

use App\Entity\SuperAdmin;
use App\Enumeration\StatutSuperAdmin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SuperAdmin>
 */
class SuperAdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuperAdmin::class);
    }

    /**
     * Récupère tous les super admins actifs
     *
     * @return SuperAdmin[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.statut = :statut')
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->orderBy('sa.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un super admin par email
     */
    public function findOneByEmail(string $email): ?SuperAdmin
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.email = :email')
            ->setParameter('email', strtolower(trim($email)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un super admin actif par email (pour l'authentification)
     */
    public function findActiveByEmail(string $email): ?SuperAdmin
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.email = :email')
            ->andWhere('sa.statut = :statut')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche par nom ou prénom (insensible à la casse)
     *
     * @return SuperAdmin[]
     */
    public function searchByName(string $name, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sa')
            ->andWhere('(LOWER(sa.nom) LIKE :name OR LOWER(sa.prenom) LIKE :name)')
            ->setParameter('name', '%' . strtolower(trim($name)) . '%')
            ->orderBy('sa.nom', 'ASC')
            ->addOrderBy('sa.prenom', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('sa.statut = :statut')
                ->setParameter('statut', StatutSuperAdmin::ACTIF);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les super admins connectés récemment
     *
     * @param int $joursMax Nombre maximum de jours depuis le dernier login
     * @return SuperAdmin[]
     */
    public function findRecentlyLoggedIn(int $joursMax = 30): array
    {
        $depuis = new \DateTime("-{$joursMax} days");

        return $this->createQueryBuilder('sa')
            ->andWhere('sa.dernierLogin >= :depuis')
            ->andWhere('sa.statut = :statut')
            ->setParameter('depuis', $depuis)
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->orderBy('sa.dernierLogin', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les super admins inactifs (non connectés depuis X jours)
     *
     * @param int $joursInactivite Nombre de jours d'inactivité
     * @return SuperAdmin[]
     */
    public function findInactive(int $joursInactivite = 90): array
    {
        $limite = new \DateTime("-{$joursInactivite} days");

        return $this->createQueryBuilder('sa')
            ->andWhere('(sa.dernierLogin IS NULL OR sa.dernierLogin < :limite)')
            ->andWhere('sa.statut = :statut')
            ->setParameter('limite', $limite)
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->orderBy('sa.dernierLogin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de super admins par statut
     *
     * @return array{actif: int, suspendu: int, total: int}
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('sa')
            ->select('sa.statut', 'COUNT(sa.id) as count')
            ->groupBy('sa.statut')
            ->getQuery()
            ->getResult();

        $stats = [
            'actif' => 0,
            'suspendu' => 0,
            'total' => 0
        ];

        foreach ($result as $row) {
            $count = (int) $row['count'];
            $stats['total'] += $count;

            match ($row['statut']) {
                StatutSuperAdmin::ACTIF => $stats['actif'] = $count,
                StatutSuperAdmin::SUSPENDU => $stats['suspendu'] = $count,
                default => null
            };
        }

        return $stats;
    }

    /**
     * Trouve les super admins créés récemment
     *
     * @param int $jours Nombre de jours
     * @param int $limit Limite de résultats
     * @return SuperAdmin[]
     */
    public function findRecentlyCreated(int $jours = 7, int $limit = 10): array
    {
        $depuis = new \DateTime("-{$jours} days");

        return $this->createQueryBuilder('sa')
            ->andWhere('sa.dateCreation >= :depuis')
            ->setParameter('depuis', $depuis)
            ->orderBy('sa.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un email est disponible
     */
    public function isEmailAvailable(string $email, ?Uuid $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.email = :email')
            ->setParameter('email', strtolower(trim($email)));

        if ($excludeId !== null) {
            $qb->andWhere('sa.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Met à jour le dernier login pour un super admin
     */
    public function updateLastLogin(SuperAdmin $superAdmin): void
    {
        $superAdmin->mettreAJourDernierLogin();
        $this->save($superAdmin, true);
    }

    /**
     * Active un super admin
     */
    public function activate(SuperAdmin $superAdmin): void
    {
        $superAdmin->activer();
        $this->save($superAdmin, true);
    }

    /**
     * Suspend un super admin
     */
    public function suspend(SuperAdmin $superAdmin): void
    {
        $superAdmin->suspendre();
        $this->save($superAdmin, true);
    }

    /**
     * Récupère un super admin par ID ou lance une exception
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function findByIdOrFail(Uuid $id): SuperAdmin
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Statistiques d'activité des super admins
     *
     * @return array{
     *     connectes_7j: int,
     *     connectes_30j: int,
     *     jamais_connectes: int,
     *     inactifs_90j: int
     * }
     */
    public function getActivityStats(): array
    {
        $maintenant = new \DateTime();
        $il7jours = (clone $maintenant)->modify('-7 days');
        $il30jours = (clone $maintenant)->modify('-30 days');
        $il90jours = (clone $maintenant)->modify('-90 days');

        return [
            'connectes_7j' => $this->countConnectedSince($il7jours),
            'connectes_30j' => $this->countConnectedSince($il30jours),
            'jamais_connectes' => $this->countNeverConnected(),
            'inactifs_90j' => $this->countInactiveSince($il90jours)
        ];
    }

    private function countConnectedSince(\DateTime $since): int
    {
        return (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.dernierLogin >= :since')
            ->andWhere('sa.statut = :statut')
            ->setParameter('since', $since)
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countNeverConnected(): int
    {
        return (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.dernierLogin IS NULL')
            ->andWhere('sa.statut = :statut')
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countInactiveSince(\DateTime $since): int
    {
        return (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('(sa.dernierLogin IS NULL OR sa.dernierLogin < :since)')
            ->andWhere('sa.statut = :statut')
            ->setParameter('since', $since)
            ->setParameter('statut', StatutSuperAdmin::ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Méthodes de base pour la gestion des entités
    public function save(SuperAdmin $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SuperAdmin $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

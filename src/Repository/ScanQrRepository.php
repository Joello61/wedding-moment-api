<?php

namespace App\Repository;

use App\Entity\ScanQr;
use App\Enumeration\TypeScan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScanQr>
 */
class ScanQrRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScanQr::class);
    }

    public function save(ScanQr $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ScanQr $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve tous les scans effectués par un invité.
     * Optimisé avec join pour éviter les requêtes N+1.
     *
     * @return ScanQr[]
     */
    public function findByInvite(int $inviteId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'o', 'i', 'c') // SELECT explicite pour optimiser
            ->innerJoin('s.invite', 'i')
            ->innerJoin('s.organisateur', 'o')
            ->leftJoin('i.couple', 'c') // Si vous avez une relation couple
            ->where('i.id = :inviteId')
            ->setParameter('inviteId', $inviteId)
            ->orderBy('s.heureScan', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les scans effectués par un organisateur.
     *
     * @return ScanQr[]
     */
    public function findByOrganisateur(int $organisateurId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'i', 'o')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('s.organisateur', 'o')
            ->where('o.id = :organisateurId')
            ->setParameter('organisateurId', $organisateurId)
            ->orderBy('s.heureScan', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtre par type de scan pour un couple donné.
     * Utilise l'index composite pour optimiser les performances.
     *
     * @return ScanQr[]
     */
    public function findByType(int $coupleId, TypeScan $typeScan): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'i', 'o')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('s.organisateur', 'o')
            ->innerJoin('i.couple', 'c') // Assuming there's a couple relation on invite
            ->where('c.id = :coupleId')
            ->andWhere('s.typeScan = :typeScan')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('typeScan', $typeScan)
            ->orderBy('s.heureScan', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Les derniers scans pour le dashboard live.
     * Utilise LIMIT pour limiter les résultats côté base de données.
     *
     * @return ScanQr[]
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'i', 'o')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('s.organisateur', 'o')
            ->orderBy('s.heureScan', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Stats sur une période donnée pour un couple.
     * Optimisé avec des index sur les dates.
     *
     * @return ScanQr[]
     */
    public function findByDateRange(int $coupleId, \DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'i', 'o')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('s.organisateur', 'o')
            ->innerJoin('i.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('s.heureScan >= :start')
            ->andWhere('s.heureScan <= :end')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.heureScan', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques groupées par type de scan pour un couple.
     * Utilise PostgreSQL pour l'agrégation côté base de données.
     */
    public function getStatsByType(int $coupleId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.typeScan as type', 'COUNT(s.id) as total')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('i.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('s.typeScan')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par jour pour un couple sur une période.
     * Utilise les fonctions PostgreSQL pour grouper par jour.
     */
    public function getStatsByDay(int $coupleId, \DateTime $start, \DateTime $end): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT DATE(s.heureScan) as day, COUNT(s.id) as total
                FROM App\Entity\ScanQr s
                INNER JOIN s.invite i
                INNER JOIN i.couple c
                WHERE c.id = :coupleId
                AND s.heureScan >= :start
                AND s.heureScan <= :end
                GROUP BY DATE(s.heureScan)
                ORDER BY day ASC
            ')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getResult();
    }

    /**
     * Recherche avancée avec critères multiples.
     * Méthode flexible pour les filtres complexes.
     */
    public function findByCriteria(
        ?int $coupleId = null,
        ?int $inviteId = null,
        ?int $organisateurId = null,
        ?TypeScan $typeScan = null,
        ?\DateTime $dateFrom = null,
        ?\DateTime $dateTo = null,
        ?string $localisation = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->select('s', 'i', 'o')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('s.organisateur', 'o')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('s.heureScan', 'DESC');

        if ($coupleId !== null) {
            $qb->innerJoin('i.couple', 'c')
                ->andWhere('c.id = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        if ($inviteId !== null) {
            $qb->andWhere('i.id = :inviteId')
                ->setParameter('inviteId', $inviteId);
        }

        if ($organisateurId !== null) {
            $qb->andWhere('o.id = :organisateurId')
                ->setParameter('organisateurId', $organisateurId);
        }

        if ($typeScan !== null) {
            $qb->andWhere('s.typeScan = :typeScan')
                ->setParameter('typeScan', $typeScan);
        }

        if ($dateFrom !== null) {
            $qb->andWhere('s.heureScan >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('s.heureScan <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        if ($localisation !== null) {
            // Utilisation de ILIKE pour PostgreSQL (insensible à la casse)
            $qb->andWhere('s.localisation ILIKE :localisation')
                ->setParameter('localisation', '%' . $localisation . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de scans pour un couple.
     * Optimisé pour les grandes tables.
     */
    public function countByCoupleId(int $coupleId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->innerJoin('s.invite', 'i')
            ->innerJoin('i.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un invité a déjà scanné pour un type donné.
     * Utile pour éviter les doubles scans.
     */
    public function hasAlreadyScanned(int $inviteId, TypeScan $typeScan): bool
    {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->innerJoin('s.invite', 'i')
            ->where('i.id = :inviteId')
            ->andWhere('s.typeScan = :typeScan')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('typeScan', $typeScan)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Méthode de base pour créer des QueryBuilder réutilisables.
     */
    private function createOptimizedQueryBuilder(string $alias = 's'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->select($alias, 'i', 'o')
            ->innerJoin($alias . '.invite', 'i')
            ->innerJoin($alias . '.organisateur', 'o');
    }
}

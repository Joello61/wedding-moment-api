<?php

namespace App\Repository;

use App\Entity\LivreOr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<LivreOr>
 */
class LivreOrRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LivreOr::class);
    }

    /**
     * Récupère les messages d'un couple avec filtrage par approbation
     *
     * @param Uuid $coupleId
     * @param bool $onlyApproved Si true, ne retourne que les messages approuvés
     * @return LivreOr[]
     */
    public function findByCouple(Uuid $coupleId, bool $onlyApproved = true): array
    {
        $qb = $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('lo.dateMessage', 'DESC');

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les derniers messages approuvés d'un couple pour l'affichage front
     *
     * @param Uuid $coupleId
     * @param int $limit
     * @return LivreOr[]
     */
    public function findRecentByCouple(Uuid $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('lo.approuve = :approuve')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('approuve', true)
            ->orderBy('lo.dateMessage', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de messages approuvés d'un couple
     *
     * @param Uuid $coupleId
     * @param bool $onlyApproved
     * @return int
     */
    public function countMessagesByCouple(Uuid $coupleId, bool $onlyApproved = true): int
    {
        $qb = $this->createQueryBuilder('lo')
            ->select('COUNT(lo.id)')
            ->andWhere('lo.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère tous les messages postés par un invité spécifique
     *
     * @param Uuid $inviteId
     * @param bool $onlyApproved
     * @return LivreOr[]
     */
    public function findByInvite(Uuid $inviteId, bool $onlyApproved = true): array
    {
        $qb = $this->createQueryBuilder('lo')
            ->andWhere('lo.invite = :inviteId')
            ->setParameter('inviteId', $inviteId)
            ->orderBy('lo.dateMessage', 'DESC');

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère un message spécifique d'un couple (sécurité multi-couple)
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return LivreOr|null
     */
    public function findByIdAndCouple(Uuid $id, Uuid $coupleId): ?LivreOr
    {
        return $this->createQueryBuilder('lo')
            ->andWhere('lo.id = :id')
            ->andWhere('lo.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les messages en attente de modération pour un couple
     *
     * @param Uuid $coupleId
     * @return LivreOr[]
     */
    public function findPendingByCouple(Uuid $coupleId): array
    {
        return $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('lo.approuve = :approuve')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('approuve', false)
            ->orderBy('lo.dateMessage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche dans les messages par contenu ou nom d'auteur
     *
     * @param Uuid $coupleId
     * @param string $query
     * @param bool $onlyApproved
     * @return LivreOr[]
     */
    public function searchByContentOrAuthor(Uuid $coupleId, string $query, bool $onlyApproved = true): array
    {
        $qb = $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('(LOWER(lo.message) LIKE :query OR LOWER(lo.nomAuteur) LIKE :query)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->orderBy('lo.dateMessage', 'DESC');

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les messages avec photos associées
     *
     * @param Uuid $coupleId
     * @param bool $onlyApproved
     * @param int|null $limit
     * @return LivreOr[]
     */
    public function findWithPhotosByCouple(Uuid $coupleId, bool $onlyApproved = true, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('lo.photoAssociee IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('lo.dateMessage', 'DESC');

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les messages dans une période donnée
     *
     * @param Uuid $coupleId
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @param bool $onlyApproved
     * @return LivreOr[]
     */
    public function findByDateRange(Uuid $coupleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate, bool $onlyApproved = true): array
    {
        $qb = $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('lo.dateMessage BETWEEN :startDate AND :endDate')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('lo.dateMessage', 'DESC');

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les messages paginés avec filtres
     *
     * @param Uuid $coupleId
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @return array{data: LivreOr[], total: int, pages: int}
     */
    public function findPaginatedByCouple(Uuid $coupleId, int $page = 1, int $limit = 10, array $filters = []): array
    {
        $qb = $this->createBaseQueryBuilder($coupleId);

        // Application des filtres
        if (isset($filters['approved']) && $filters['approved'] !== null) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', (bool) $filters['approved']);
        } else {
            // Par défaut, uniquement les approuvés
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('(LOWER(lo.message) LIKE :search OR LOWER(lo.nomAuteur) LIKE :search)')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('lo.dateMessage >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('lo.dateMessage <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        if (!empty($filters['withPhoto'])) {
            $qb->andWhere('lo.photoAssociee IS NOT NULL');
        }

        if (!empty($filters['inviteId'])) {
            $qb->andWhere('lo.invite = :inviteId')
                ->setParameter('inviteId', $filters['inviteId']);
        }

        // Compte total
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(lo.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Résultats paginés
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('lo.dateMessage', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
            'pages' => (int) ceil($total / $limit)
        ];
    }

    /**
     * Statistiques des messages pour un couple
     *
     * @param Uuid $coupleId
     * @return array{total: int, approved: int, pending: int, with_photos: int}
     */
    public function getStatsByCouple(Uuid $coupleId): array
    {
        $result = $this->createQueryBuilder('lo')
            ->select([
                'COUNT(lo.id) as total',
                'SUM(CASE WHEN lo.approuve = true THEN 1 ELSE 0 END) as approved',
                'SUM(CASE WHEN lo.approuve = false THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN lo.photoAssociee IS NOT NULL THEN 1 ELSE 0 END) as with_photos'
            ])
            ->andWhere('lo.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'approved' => (int) $result['approved'],
            'pending' => (int) $result['pending'],
            'with_photos' => (int) $result['with_photos']
        ];
    }

    /**
     * Approuve ou rejette un message
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @param bool $approved
     * @return bool True si le message a été trouvé et modifié
     */
    public function setApprovalStatus(Uuid $id, Uuid $coupleId, bool $approved): bool
    {
        $affected = $this->getEntityManager()
            ->createQueryBuilder()
            ->update(LivreOr::class, 'lo')
            ->set('lo.approuve', ':approved')
            ->andWhere('lo.id = :id')
            ->andWhere('lo.couple = :coupleId')
            ->setParameter('approved', $approved)
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    /**
     * Approuve tous les messages en attente d'un couple
     *
     * @param Uuid $coupleId
     * @return int Nombre de messages approuvés
     */
    public function approveAllPending(Uuid $coupleId): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(LivreOr::class, 'lo')
            ->set('lo.approuve', ':approved')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('lo.approuve = :pending')
            ->setParameter('approved', true)
            ->setParameter('pending', false)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les derniers messages de tous les couples (admin)
     *
     * @param int $limit
     * @param bool $onlyApproved
     * @return LivreOr[]
     */
    public function findRecentGlobal(int $limit = 10, bool $onlyApproved = true): array
    {
        $qb = $this->createQueryBuilder('lo')
            ->join('lo.couple', 'c')
            ->orderBy('lo.dateMessage', 'DESC')
            ->setMaxResults($limit);

        if ($onlyApproved) {
            $qb->andWhere('lo.approuve = :approuve')
                ->setParameter('approuve', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les auteurs les plus actifs d'un couple
     *
     * @param Uuid $coupleId
     * @param int $limit
     * @return array
     */
    public function findTopAuthorsByCouple(Uuid $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('lo')
            ->select('lo.nomAuteur', 'COUNT(lo.id) as message_count')
            ->andWhere('lo.couple = :coupleId')
            ->andWhere('lo.approuve = :approuve')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('approuve', true)
            ->groupBy('lo.nomAuteur')
            ->orderBy('message_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité LivreOr
     *
     * @param LivreOr $entity
     * @param bool $flush
     */
    public function save(LivreOr $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité LivreOr
     *
     * @param LivreOr $entity
     * @param bool $flush
     */
    public function remove(LivreOr $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * QueryBuilder de base pour éviter la duplication
     *
     * @param Uuid $coupleId
     * @return QueryBuilder
     */
    private function createBaseQueryBuilder(Uuid $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('lo')
            ->andWhere('lo.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);
    }
}

<?php

namespace App\Repository;

use App\Entity\HistoireCouple;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<HistoireCouple>
 */
class HistoireCoupleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoireCouple::class);
    }

    /**
     * Récupère toutes les histoires actives d'un couple
     *
     * @param Uuid $coupleId
     * @return HistoireCouple[]
     */
    public function findByCouple(Uuid $coupleId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->andWhere('h.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('h.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les histoires d'un couple triées par ordre d'affichage
     *
     * @param Uuid $coupleId
     * @param bool $activeOnly
     * @return HistoireCouple[]
     */
    public function findOrderedByCouple(Uuid $coupleId, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($activeOnly) {
            $qb->andWhere('h.actif = :actif')
                ->setParameter('actif', true);
        }

        return $qb->orderBy('h.ordreAffichage', 'ASC')
            ->addOrderBy('h.dateEvenement', 'ASC')
            ->addOrderBy('h.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les dernières histoires créées (pour affichage front)
     *
     * @param int $limit
     * @param Uuid|null $coupleId Optionnel pour filtrer par couple
     * @return HistoireCouple[]
     */
    public function findRecent(int $limit = 5, ?Uuid $coupleId = null): array
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('h.dateCreation', 'DESC')
            ->setMaxResults($limit);

        if ($coupleId !== null) {
            $qb->andWhere('h.couple = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère une histoire spécifique d'un couple (sécurité multi-couple)
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return HistoireCouple|null
     */
    public function findByIdAndCouple(Uuid $id, Uuid $coupleId): ?HistoireCouple
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.id = :id')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère une histoire par ID ou lance une exception
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return HistoireCouple
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function findByIdAndCoupleOrFail(Uuid $id, Uuid $coupleId): HistoireCouple
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.id = :id')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Récupère les histoires avec une date d'événement dans une période
     *
     * @param Uuid $coupleId
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return HistoireCouple[]
     */
    public function findByEventDateRange(Uuid $coupleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->andWhere('h.actif = :actif')
            ->andWhere('h.dateEvenement BETWEEN :startDate AND :endDate')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('h.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les histoires chronologiques (avec date d'événement)
     *
     * @param Uuid $coupleId
     * @return HistoireCouple[]
     */
    public function findChronologicalByCouple(Uuid $coupleId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->andWhere('h.actif = :actif')
            ->andWhere('h.dateEvenement IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('h.dateEvenement', 'ASC')
            ->addOrderBy('h.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par titre ou contenu
     *
     * @param Uuid $coupleId
     * @param string $query
     * @return HistoireCouple[]
     */
    public function searchByTitleOrContent(Uuid $coupleId, string $query): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->andWhere('h.actif = :actif')
            ->andWhere('(LOWER(h.titre) LIKE :query OR LOWER(h.contenu) LIKE :query)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->orderBy('h.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre d'histoires d'un couple
     *
     * @param Uuid $coupleId
     * @param bool $activeOnly
     * @return int
     */
    public function countByCouple(Uuid $coupleId, bool $activeOnly = true): int
    {
        $qb = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($activeOnly) {
            $qb->andWhere('h.actif = :actif')
                ->setParameter('actif', true);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les histoires avec photos
     *
     * @param Uuid $coupleId
     * @param int|null $limit
     * @return HistoireCouple[]
     */
    public function findWithPhotosByCouple(Uuid $coupleId, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->andWhere('h.actif = :actif')
            ->andWhere('h.photo IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('h.dateCreation', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les histoires paginées d'un couple
     *
     * @param Uuid $coupleId
     * @param int $page
     * @param int $limit
     * @param bool $activeOnly
     * @param string $orderBy
     * @return array{data: HistoireCouple[], total: int, pages: int}
     */
    public function findPaginatedByCouple(
        Uuid $coupleId,
        int $page = 1,
        int $limit = 10,
        bool $activeOnly = true,
        string $orderBy = 'creation'
    ): array {
        $qb = $this->createBaseQueryBuilder($coupleId, $activeOnly);

        // Compte total
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(h.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Tri selon le paramètre
        match ($orderBy) {
            'ordre' => $qb->orderBy('h.ordreAffichage', 'ASC')
                ->addOrderBy('h.dateEvenement', 'ASC'),
            'evenement' => $qb->orderBy('h.dateEvenement', 'ASC')
                ->addOrderBy('h.ordreAffichage', 'ASC'),
            'titre' => $qb->orderBy('h.titre', 'ASC'),
            default => $qb->orderBy('h.dateCreation', 'DESC')
        };

        // Résultats paginés
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
            'pages' => (int) ceil($total / $limit)
        ];
    }

    /**
     * Trouve le prochain ordre d'affichage disponible
     *
     * @param Uuid $coupleId
     * @return int
     */
    public function findNextDisplayOrder(Uuid $coupleId): int
    {
        $maxOrder = $this->createQueryBuilder('h')
            ->select('MAX(h.ordreAffichage)')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Réorganise les ordres d'affichage d'un couple
     *
     * @param Uuid $coupleId
     * @param array $histoireIds Tableau d'IDs dans l'ordre souhaité
     */
    public function reorderHistoires(Uuid $coupleId, array $histoireIds): void
    {
        $em = $this->getEntityManager();
        $ordre = 1;

        foreach ($histoireIds as $histoireId) {
            $em->createQueryBuilder()
                ->update(HistoireCouple::class, 'h')
                ->set('h.ordreAffichage', ':ordre')
                ->set('h.dateMiseAJour', ':now')
                ->andWhere('h.id = :id')
                ->andWhere('h.couple = :coupleId')
                ->setParameter('ordre', $ordre)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $histoireId)
                ->setParameter('coupleId', $coupleId)
                ->getQuery()
                ->execute();

            $ordre++;
        }
    }

    /**
     * Active ou désactive une histoire
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @param bool $actif
     * @return bool True si l'histoire a été trouvée et modifiée
     */
    public function toggleActive(Uuid $id, Uuid $coupleId, bool $actif): bool
    {
        $affected = $this->getEntityManager()
            ->createQueryBuilder()
            ->update(HistoireCouple::class, 'h')
            ->set('h.actif', ':actif')
            ->set('h.dateMiseAJour', ':now')
            ->andWhere('h.id = :id')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('actif', $actif)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    /**
     * Sauvegarde une entité HistoireCouple
     *
     * @param HistoireCouple $entity
     * @param bool $flush
     */
    public function save(HistoireCouple $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité HistoireCouple
     *
     * @param HistoireCouple $entity
     * @param bool $flush
     */
    public function remove(HistoireCouple $entity, bool $flush = false): void
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
     * @param bool $activeOnly
     * @return QueryBuilder
     */
    private function createBaseQueryBuilder(Uuid $coupleId, bool $activeOnly = true): QueryBuilder
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($activeOnly) {
            $qb->andWhere('h.actif = :actif')
                ->setParameter('actif', true);
        }

        return $qb;
    }
}

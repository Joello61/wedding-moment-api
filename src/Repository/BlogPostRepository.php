<?php

namespace App\Repository;

use App\Entity\BlogPost;
use App\Enumeration\StatutBlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    /**
     * Récupère tous les posts d'un couple avec filtrage optionnel par statut
     *
     * @param Uuid $coupleId
     * @param StatutBlogPost|null $statut
     * @return BlogPost[]
     */
    public function findByCouple(Uuid $coupleId, ?StatutBlogPost $statut = null): array
    {
        $qb = $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('bp.dateCreation', 'DESC');

        if ($statut !== null) {
            $qb->andWhere('bp.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère uniquement les posts publiés d'un couple
     *
     * @param Uuid $coupleId
     * @return BlogPost[]
     */
    public function findPublishedByCouple(Uuid $coupleId): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->andWhere('bp.statut = :statut')
            ->andWhere('bp.datePublication IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', StatutBlogPost::PUBLIE)
            ->orderBy('bp.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les derniers posts publiés d'un couple
     *
     * @param Uuid $coupleId
     * @param int $limit
     * @return BlogPost[]
     */
    public function findRecentByCouple(Uuid $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->andWhere('bp.statut = :statut')
            ->andWhere('bp.datePublication IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', StatutBlogPost::PUBLIE)
            ->orderBy('bp.datePublication', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par titre ou contenu (optimisé pour PostgreSQL)
     *
     * @param Uuid $coupleId
     * @param string $query
     * @param StatutBlogPost|null $statut
     * @return BlogPost[]
     */
    public function searchByTitleOrContent(Uuid $coupleId, string $query, ?StatutBlogPost $statut = null): array
    {
        $qb = $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->andWhere('(LOWER(bp.titre) LIKE :query OR LOWER(bp.contenu) LIKE :query)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->orderBy('bp.dateCreation', 'DESC');

        if ($statut !== null) {
            $qb->andWhere('bp.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche full-text optimisée pour PostgreSQL (si disponible)
     *
     * @param Uuid $coupleId
     * @param string $query
     * @return BlogPost[]
     */
    public function searchFullText(Uuid $coupleId, string $query): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->andWhere('bp.statut = :statut')
            ->andWhere("to_tsvector('french', bp.titre || ' ' || bp.contenu) @@ plainto_tsquery('french', :query)")
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', StatutBlogPost::PUBLIE)
            ->setParameter('query', $query)
            ->orderBy('bp.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de posts d'un couple
     *
     * @param Uuid $coupleId
     * @param StatutBlogPost|null $statut
     * @return int
     */
    public function countByCouple(Uuid $coupleId, ?StatutBlogPost $statut = null): int
    {
        $qb = $this->createQueryBuilder('bp')
            ->select('COUNT(bp.id)')
            ->andWhere('bp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($statut !== null) {
            $qb->andWhere('bp.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère un post spécifique d'un couple (sécurité multi-couple)
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return BlogPost|null
     */
    public function findByIdAndCouple(Uuid $id, Uuid $coupleId): ?BlogPost
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.id = :id')
            ->andWhere('bp.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les posts avec pagination
     *
     * @param Uuid $coupleId
     * @param int $page
     * @param int $limit
     * @param StatutBlogPost|null $statut
     * @return array{data: BlogPost[], total: int, pages: int}
     */
    public function findPaginatedByCouple(Uuid $coupleId, int $page = 1, int $limit = 10, ?StatutBlogPost $statut = null): array
    {
        $qb = $this->createBaseQueryBuilder($coupleId, $statut);

        // Compte total
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(bp.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Résultats paginés
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('bp.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
            'pages' => (int) ceil($total / $limit)
        ];
    }

    /**
     * Récupère les statistiques des posts d'un couple
     *
     * @param Uuid $coupleId
     * @return array{total: int, publies: int, brouillons: int, archives: int}
     */
    public function getStatsByCouple(Uuid $coupleId): array
    {
        $result = $this->createQueryBuilder('bp')
            ->select('bp.statut', 'COUNT(bp.id) as count')
            ->andWhere('bp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('bp.statut')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'publies' => 0,
            'brouillons' => 0,
            'archives' => 0
        ];

        foreach ($result as $row) {
            $count = (int) $row['count'];
            $stats['total'] += $count;

            match ($row['statut']) {
                StatutBlogPost::PUBLIE => $stats['publies'] = $count,
                StatutBlogPost::BROUILLON => $stats['brouillons'] = $count,
                StatutBlogPost::ARCHIVE => $stats['archives'] = $count,
                default => null
            };
        }

        return $stats;
    }

    /**
     * Trouve les posts similaires basés sur le titre
     *
     * @param Uuid $coupleId
     * @param string $titre
     * @param Uuid|null $excludeId
     * @return BlogPost[]
     */
    public function findSimilarPosts(Uuid $coupleId, string $titre, ?Uuid $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->andWhere('bp.statut = :statut')
            ->andWhere('LOWER(bp.titre) LIKE :titre')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', StatutBlogPost::PUBLIE)
            ->setParameter('titre', '%' . strtolower($titre) . '%')
            ->setMaxResults(5);

        if ($excludeId !== null) {
            $qb->andWhere('bp.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les posts publiés dans une période donnée
     *
     * @param Uuid $coupleId
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return BlogPost[]
     */
    public function findPublishedBetweenDates(Uuid $coupleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->andWhere('bp.statut = :statut')
            ->andWhere('bp.datePublication BETWEEN :startDate AND :endDate')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', StatutBlogPost::PUBLIE)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('bp.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité BlogPost
     *
     * @param BlogPost $entity
     * @param bool $flush
     */
    public function save(BlogPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité BlogPost
     *
     * @param BlogPost $entity
     * @param bool $flush
     */
    public function remove(BlogPost $entity, bool $flush = false): void
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
     * @param StatutBlogPost|null $statut
     * @return QueryBuilder
     */
    private function createBaseQueryBuilder(Uuid $coupleId, ?StatutBlogPost $statut = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('bp')
            ->andWhere('bp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($statut !== null) {
            $qb->andWhere('bp.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb;
    }
}

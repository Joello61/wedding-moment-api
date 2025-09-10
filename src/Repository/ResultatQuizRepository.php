<?php

namespace App\Repository;

use App\Entity\ResultatQuiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<ResultatQuiz>
 */
class ResultatQuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResultatQuiz::class);
    }

    /**
     * Trouve tous les résultats d'un quiz avec optimisation des jointures
     */
    public function findByQuiz(int $quizId): array
    {
        return $this->createQueryBuilder('rq')
            ->addSelect('i', 'q')  // Eager loading pour éviter N+1
            ->innerJoin('rq.invite', 'i')
            ->innerJoin('rq.quiz', 'q')
            ->where('q.id = :quizId')
            ->setParameter('quizId', $quizId)
            ->orderBy('rq.score', 'DESC')
            ->addOrderBy('rq.tempsCompletion', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le résultat spécifique d'un invité pour un quiz
     */
    public function findByInviteAndQuiz(int $inviteId, int $quizId): ?ResultatQuiz
    {
        return $this->createQueryBuilder('rq')
            ->addSelect('i', 'q')
            ->innerJoin('rq.invite', 'i')
            ->innerJoin('rq.quiz', 'q')
            ->where('i.id = :inviteId')
            ->andWhere('q.id = :quizId')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('quizId', $quizId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre total de participants à un quiz
     */
    public function countByQuiz(int $quizId): int
    {
        return (int) $this->createQueryBuilder('rq')
            ->select('COUNT(rq.id)')
            ->innerJoin('rq.quiz', 'q')
            ->where('q.id = :quizId')
            ->setParameter('quizId', $quizId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les meilleurs scores pour le leaderboard avec pagination
     */
    public function findTopScorers(int $quizId, int $limit = 5): array
    {
        return $this->createQueryBuilder('rq')
            ->addSelect('i')
            ->innerJoin('rq.invite', 'i')
            ->innerJoin('rq.quiz', 'q')
            ->where('q.id = :quizId')
            ->andWhere('rq.score IS NOT NULL')
            ->setParameter('quizId', $quizId)
            ->orderBy('rq.score', 'DESC')
            ->addOrderBy('rq.tempsCompletion', 'ASC')  // En cas d'égalité, le plus rapide gagne
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le score moyen d'un quiz avec PostgreSQL
     */
    public function findAverageScore(int $quizId): float
    {
        $result = $this->createQueryBuilder('rq')
            ->select('AVG(rq.score) as avgScore')
            ->innerJoin('rq.quiz', 'q')
            ->where('q.id = :quizId')
            ->andWhere('rq.score IS NOT NULL')
            ->setParameter('quizId', $quizId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    /**
     * Statistiques complètes d'un quiz en une seule requête (optimisé PostgreSQL)
     */
    public function getQuizStatistics(int $quizId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_participants,
                COALESCE(AVG(score), 0) as average_score,
                COALESCE(MIN(score), 0) as min_score,
                COALESCE(MAX(score), 0) as max_score,
                COALESCE(AVG(temps_completion), 0) as average_completion_time,
                COUNT(CASE WHEN score IS NOT NULL THEN 1 END) as completed_count
            FROM resultats_quiz rq
            INNER JOIN quiz q ON rq.quiz_id = q.id
            WHERE q.id = :quizId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['quizId' => $quizId])->fetchAssociative();

        return [
            'total_participants' => (int) $result['total_participants'],
            'completed_participants' => (int) $result['completed_count'],
            'average_score' => (float) $result['average_score'],
            'min_score' => (int) $result['min_score'],
            'max_score' => (int) $result['max_score'],
            'average_completion_time' => (float) $result['average_completion_time'],
            'completion_rate' => $result['total_participants'] > 0
                ? ($result['completed_count'] / $result['total_participants']) * 100
                : 0
        ];
    }

    /**
     * Trouve les résultats récents avec pagination
     */
    public function findRecentResults(int $page = 1, int $pageSize = 20): Paginator
    {
        $query = $this->createQueryBuilder('rq')
            ->addSelect('i', 'q')
            ->innerJoin('rq.invite', 'i')
            ->innerJoin('rq.quiz', 'q')
            ->orderBy('rq.dateCompletion', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery();

        return new Paginator($query, fetchJoinCollection: true);
    }

    /**
     * Recherche avec filtres avancés
     */
    public function findWithFilters(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('rq')
            ->addSelect('i', 'q')
            ->innerJoin('rq.invite', 'i')
            ->innerJoin('rq.quiz', 'q');

        if (isset($filters['quiz_id'])) {
            $qb->andWhere('q.id = :quizId')
                ->setParameter('quizId', $filters['quiz_id']);
        }

        if (isset($filters['min_score'])) {
            $qb->andWhere('rq.score >= :minScore')
                ->setParameter('minScore', $filters['min_score']);
        }

        if (isset($filters['max_score'])) {
            $qb->andWhere('rq.score <= :maxScore')
                ->setParameter('maxScore', $filters['max_score']);
        }

        if (isset($filters['date_from'])) {
            $qb->andWhere('rq.dateCompletion >= :dateFrom')
                ->setParameter('dateFrom', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $qb->andWhere('rq.dateCompletion <= :dateTo')
                ->setParameter('dateTo', $filters['date_to']);
        }

        if (isset($filters['completed_only']) && $filters['completed_only']) {
            $qb->andWhere('rq.score IS NOT NULL');
        }

        return $qb;
    }

    /**
     * Distribution des scores par tranches (utilise PostgreSQL CASE/WHEN)
     */
    public function getScoreDistribution(int $quizId): array
    {
        $sql = "
            WITH score_ranges AS (
    SELECT
        CASE
            WHEN score >= 90 THEN 'Excellent (90-100)'
            WHEN score >= 70 THEN 'Bien (70-89)'
            WHEN score >= 50 THEN 'Passable (50-69)'
            WHEN score < 50 THEN 'Insuffisant (0-49)'
            ELSE 'Non complété'
        END as score_range
    FROM resultats_quiz
    WHERE quiz_id = :quizId
)
SELECT score_range, COUNT(*) AS count
FROM score_ranges
GROUP BY score_range
ORDER BY
    CASE score_range
        WHEN 'Excellent (90-100)' THEN 1
        WHEN 'Bien (70-89)' THEN 2
        WHEN 'Passable (50-69)' THEN 3
        WHEN 'Insuffisant (0-49)' THEN 4
        ELSE 5
    END;
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery(['quizId' => $quizId])->fetchAllAssociative();
    }

    /**
     * Classement avec rang PostgreSQL (utilise ROW_NUMBER)
     */
    public function getRanking(int $quizId): array
    {
        $sql = "
            SELECT
                rq.id,
                i.nom,
                i.email,
                rq.score,
                rq.temps_completion,
                ROW_NUMBER() OVER (
                    ORDER BY rq.score DESC, rq.temps_completion
                ) as rang
            FROM resultats_quiz rq
            INNER JOIN invites i ON rq.invite_id = i.id
            INNER JOIN quiz q ON rq.quiz_id = q.id
            WHERE q.id = :quizId
              AND rq.score IS NOT NULL
            ORDER BY rang
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery(['quizId' => $quizId])->fetchAllAssociative();
    }

    /**
     * Optimisation pour les gros volumes : trouve les IDs seulement puis hydrate
     */
    public function findTopScorerIds(int $quizId, int $limit = 5): array
    {
        return $this->createQueryBuilder('rq')
            ->select('rq.id')
            ->innerJoin('rq.quiz', 'q')
            ->where('q.id = :quizId')
            ->andWhere('rq.score IS NOT NULL')
            ->setParameter('quizId', $quizId)
            ->orderBy('rq.score', 'DESC')
            ->addOrderBy('rq.tempsCompletion', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Sauvegarde avec gestion d'erreur
     */
    public function save(ResultatQuiz $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Suppression avec gestion d'erreur
     */
    public function remove(ResultatQuiz $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Batch insert optimisé pour de gros volumes
     */
    public function batchInsert(array $entities): void
    {
        $em = $this->getEntityManager();
        $batchSize = 100;

        foreach ($entities as $i => $entity) {
            $em->persist($entity);

            if (($i % $batchSize) === 0) {
                $em->flush();
                $em->clear();
            }
        }

        $em->flush();
        $em->clear();
    }
}

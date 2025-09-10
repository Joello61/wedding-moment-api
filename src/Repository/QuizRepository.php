<?php

namespace App\Repository;

use App\Entity\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Repository pour la gestion des quiz des couples.
 *
 * @extends ServiceEntityRepository<Quiz>
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    /**
     * Trouve tous les quiz créés par un couple.
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('q.dateMiseAJour', 'DESC')
            ->addOrderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les quiz actifs d'un couple.
     */
    public function findActiveByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('q.dateMiseAJour', 'DESC')
            ->addOrderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère un quiz spécifique d'un couple (sécurité multi-couple).
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?Quiz
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.id = :id')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche par titre dans les quiz d'un couple.
     */
    public function searchByTitle(int $coupleId, string $query): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->andWhere('LOWER(q.titre) LIKE LOWER(:query) OR LOWER(q.description) LIKE LOWER(:query)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('q.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les derniers quiz pour affichage front.
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('q.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les quiz avec leurs résultats (optimisé pour statistiques).
     */
    public function findWithResults(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.resultats', 'r')
            ->addSelect('r')
            ->leftJoin('r.invite', 'i')
            ->addSelect('i')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('q.dateMiseAJour', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les quiz populaires (avec le plus de réponses).
     */
    public function findMostPopular(int $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('q')
            ->select('q', 'COUNT(r.id) as responseCount')
            ->leftJoin('q.resultats', 'r')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('q.id')
            ->orderBy('responseCount', 'DESC')
            ->addOrderBy('q.dateMiseAJour', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche dans le contenu des questions JSONB (PostgreSQL optimisé).
     */
    public function searchInQuestions(int $coupleId, string $searchTerm): array
    {
        $sql = '
            SELECT q.* FROM quiz q
            WHERE q.couple_id = :coupleId
            AND q.actif = :actif
            AND (
                LOWER(q.titre) LIKE LOWER(:searchTerm)
                OR LOWER(q.description) LIKE LOWER(:searchTerm)
                OR q.questions_json::text ILIKE :searchTerm
            )
            ORDER BY q.$dateMiseAJour DESC
        ';

        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addEntityResult(Quiz::class, 'q');
        $rsm->addFieldResult('q', 'id', 'id');
        $rsm->addFieldResult('q', 'couple_id', 'couple');
        $rsm->addFieldResult('q', 'titre', 'titre');
        $rsm->addFieldResult('q', 'description', 'description');
        $rsm->addFieldResult('q', 'questions_json', 'questionsJson');
        $rsm->addFieldResult('q', 'actif', 'actif');
        $rsm->addFieldResult('q', 'date_creation', 'dateCreation');
        $rsm->addFieldResult('q', 'date_mise_a_jour', 'dateMiseAJour');

        return $this->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->getResult();
    }

    /**
     * Trouve les quiz par nombre de questions.
     */
    public function findByQuestionCount(int $coupleId, int $minQuestions = null, int $maxQuestions = null): array
    {
        $sql = '
            SELECT q.* FROM quiz q
            WHERE q.couple_id = :coupleId
            AND q.actif = :actif
        ';

        $params = [
            'coupleId' => $coupleId,
            'actif' => true
        ];

        if ($minQuestions !== null) {
            $sql .= ' AND jsonb_array_length(q.questions_json) >= :minQuestions';
            $params['minQuestions'] = $minQuestions;
        }

        if ($maxQuestions !== null) {
            $sql .= ' AND jsonb_array_length(q.questions_json) <= :maxQuestions';
            $params['maxQuestions'] = $maxQuestions;
        }

        $sql .= ' ORDER BY jsonb_array_length(q.questions_json) DESC, q.date_mise_a_jour DESC';

        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addEntityResult(Quiz::class, 'q');
        $rsm->addFieldResult('q', 'id', 'id');
        $rsm->addFieldResult('q', 'couple_id', 'couple');
        $rsm->addFieldResult('q', 'titre', 'titre');
        $rsm->addFieldResult('q', 'description', 'description');
        $rsm->addFieldResult('q', 'questions_json', 'questionsJson');
        $rsm->addFieldResult('q', 'actif', 'actif');
        $rsm->addFieldResult('q', 'date_creation', 'dateCreation');
        $rsm->addFieldResult('q', 'date_mise_a_jour', 'dateMiseAJour');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);

        foreach ($params as $param => $value) {
            $query->setParameter($param, $value);
        }

        return $query->getResult();
    }

    /**
     * Statistiques détaillées des quiz pour un couple.
     */
    public function getStats(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->select([
                'COUNT(q.id) as totalQuiz',
                'SUM(CASE WHEN q.actif = true THEN 1 ELSE 0 END) as activeQuiz',
                'SUM(CASE WHEN q.actif = false THEN 1 ELSE 0 END) as inactiveQuiz'
            ])
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Statistiques avancées avec comptage des réponses.
     */
    public function getAdvancedStats(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->select([
                'COUNT(q.id) as totalQuiz',
                'SUM(CASE WHEN q.actif = true THEN 1 ELSE 0 END) as activeQuiz',
                'COUNT(DISTINCT r.id) as totalResponses',
                'COUNT(DISTINCT r.invite) as uniqueRespondents',
                'AVG(r.score) as averageScore'
            ])
            ->leftJoin('q.resultats', 'r')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les quiz récemment mis à jour.
     */
    public function findRecentlyUpdated(int $coupleId, int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('q')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.dateMiseAJour >= :since')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('since', $since)
            ->orderBy('q.dateMiseAJour', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les quiz sans réponses.
     */
    public function findWithoutResponses(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.resultats', 'r')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->andWhere('r.id IS NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de réponses par quiz.
     */
    public function getResponseCounts(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.id', 'q.titre', 'COUNT(r.id) as responseCount')
            ->leftJoin('q.resultats', 'r')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('q.id', 'q.titre')
            ->orderBy('responseCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les quiz avec pagination et filtres.
     */
    public function findWithFilters(
        int $coupleId,
        ?bool $actif = null,
        ?string $searchTerm = null,
        int $limit = 10,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($actif !== null) {
            $qb->andWhere('q.actif = :actif')
                ->setParameter('actif', $actif);
        }

        if ($searchTerm !== null) {
            $qb->andWhere('LOWER(q.titre) LIKE LOWER(:searchTerm) OR LOWER(q.description) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->orderBy('q.dateMiseAJour', 'DESC')
            ->addOrderBy('q.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les quiz avec filtres.
     */
    public function countWithFilters(
        int $coupleId,
        ?bool $actif = null,
        ?string $searchTerm = null
    ): int {
        $qb = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($actif !== null) {
            $qb->andWhere('q.actif = :actif')
                ->setParameter('actif', $actif);
        }

        if ($searchTerm !== null) {
            $qb->andWhere('LOWER(q.titre) LIKE LOWER(:searchTerm) OR LOWER(q.description) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Active/désactive un quiz.
     */
    public function updateStatus(Uuid $quizId, bool $actif): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(Quiz::class, 'q')
            ->set('q.actif', ':actif')
            ->set('q.dateMiseAJour', ':now')
            ->where('q.id = :id')
            ->setParameter('actif', $actif)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $quizId, 'uuid')
            ->getQuery()
            ->execute();
    }

    /**
     * Duplique un quiz.
     */
    public function duplicateQuiz(Uuid $quizId, string $newTitle): ?Quiz
    {
        $originalQuiz = $this->find($quizId);
        if (!$originalQuiz) {
            return null;
        }

        $newQuiz = new Quiz();
        $newQuiz->setCouple($originalQuiz->getCouple());
        $newQuiz->setTitre($newTitle);
        $newQuiz->setDescription($originalQuiz->getDescription());
        $newQuiz->setQuestionsJson($originalQuiz->getQuestionsJson());
        $newQuiz->setActif(false); // Nouveau quiz désactivé par défaut

        $this->save($newQuiz, true);

        return $newQuiz;
    }

    /**
     * Analyse de performance des quiz (temps de réponse moyen).
     */
    public function getPerformanceAnalysis(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->select([
                'q.id',
                'q.titre',
                'COUNT(r.id) as totalResponses',
                'AVG(r.score) as averageScore',
                'MIN(r.score) as minScore',
                'MAX(r.score) as maxScore',
                'AVG(r.tempsPasse) as averageTime'
            ])
            ->leftJoin('q.resultats', 'r')
            ->andWhere('q.couple = :coupleId')
            ->andWhere('q.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('q.id', 'q.titre')
            ->having('COUNT(r.id) > 0')
            ->orderBy('averageScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Exporte les données des quiz pour analyse externe.
     */
    public function exportQuizData(int $coupleId): array
    {
        return $this->createQueryBuilder('q')
            ->select([
                'q.id',
                'q.titre',
                'q.description',
                'q.questionsJson',
                'q.dateCreation',
                'q.dateMiseAJour',
                'COUNT(r.id) as responseCount'
            ])
            ->leftJoin('q.resultats', 'r')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('q.id', 'q.titre', 'q.description', 'q.questionsJson', 'q.dateCreation', 'q.dateMiseAJour')
            ->orderBy('q.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder de base pour les requêtes complexes.
     */
    public function createBaseQueryBuilder(int $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);
    }

    /**
     * Sauvegarde une entité Quiz.
     */
    public function save(Quiz $quiz, bool $flush = false): void
    {
        $this->getEntityManager()->persist($quiz);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité Quiz.
     */
    public function remove(Quiz $quiz, bool $flush = false): void
    {
        $this->getEntityManager()->remove($quiz);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

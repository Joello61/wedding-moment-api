<?php

namespace App\Repository;

use App\Entity\ReponseSondage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des réponses aux sondages.
 *
 * @extends ServiceEntityRepository<ReponseSondage>
 */
class ReponseSondageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReponseSondage::class);
    }

    /**
     * Trouve toutes les réponses d'un sondage avec les données des invités.
     *
     * @param int $sondageId L'ID du sondage
     * @return ReponseSondage[]
     */
    public function findBySondage(int $sondageId): array
    {
        return $this->createQueryBuilder('rs')
            ->innerJoin('rs.sondage', 's')
            ->addSelect('s')
            ->innerJoin('rs.invite', 'i')
            ->addSelect('i')
            ->where('rs.sondage = :sondageId')
            ->setParameter('sondageId', $sondageId)
            ->orderBy('rs.dateReponse', 'ASC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Trouve la réponse spécifique d'un invité à un sondage.
     *
     * @param int $inviteId L'ID de l'invité
     * @param int $sondageId L'ID du sondage
     * @return ReponseSondage|null
     */
    public function findByInviteAndSondage(int $inviteId, int $sondageId): ?ReponseSondage
    {
        return $this->createQueryBuilder('rs')
            ->innerJoin('rs.sondage', 's')
            ->addSelect('s')
            ->innerJoin('rs.invite', 'i')
            ->addSelect('i')
            ->where('rs.invite = :inviteId')
            ->andWhere('rs.sondage = :sondageId')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('sondageId', $sondageId)
            ->getQuery()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre total de réponses pour un sondage.
     *
     * @param int $sondageId L'ID du sondage
     * @return int Le nombre de réponses
     */
    public function countResponses(int $sondageId): int
    {
        return (int) $this->createQueryBuilder('rs')
            ->select('COUNT(rs.id)')
            ->where('rs.sondage = :sondageId')
            ->setParameter('sondageId', $sondageId)
            ->getQuery()
            ->useQueryCache(true)
            ->getSingleScalarResult();
    }

    /**
     * Génère un résumé des réponses pour l'affichage graphique.
     * Optimisé pour PostgreSQL avec agrégations avancées.
     *
     * @param int $sondageId L'ID du sondage
     * @return array{total_responses: int, response_distribution: array, recent_responses: int, completion_rate: float}
     */
    public function findSummary(int $sondageId): array
    {
        // Statistiques de base avec une seule requête optimisée
        $baseStats = $this->createQueryBuilder('rs')
            ->select('
                COUNT(rs.id) as total_responses,
                COUNT(CASE WHEN rs.reponse IS NOT NULL AND rs.reponse != \'\' THEN 1 END) as complete_responses,
                COUNT(CASE WHEN rs.dateReponse >= :last_week THEN 1 END) as recent_responses
            ')
            ->where('rs.sondage = :sondageId')
            ->setParameter('sondageId', $sondageId)
            ->setParameter('last_week', new \DateTime('-7 days'))
            ->getQuery()
            ->useQueryCache(true)
            ->getSingleResult();

        $totalResponses = (int) $baseStats['total_responses'];
        $completeResponses = (int) $baseStats['complete_responses'];
        $recentResponses = (int) $baseStats['recent_responses'];

        // Distribution des réponses avec agrégation PostgreSQL
        $responseDistribution = $this->getResponseDistribution($sondageId);

        return [
            'total_responses' => $totalResponses,
            'complete_responses' => $completeResponses,
            'recent_responses' => $recentResponses,
            'completion_rate' => $totalResponses > 0 ? round(($completeResponses / $totalResponses) * 100, 2) : 0.0,
            'response_distribution' => $responseDistribution,
        ];
    }

    /**
     * Obtient la distribution des réponses pour un sondage.
     * Utilise les capacités d'agrégation PostgreSQL pour optimiser les performances.
     *
     * @param int $sondageId L'ID du sondage
     * @return array<array{reponse: string, count: int, percentage: float}>
     */
    private function getResponseDistribution(int $sondageId): array
    {
        // Utilisation d'une requête PostgreSQL optimisée avec fenêtrage
        $results = $this->createQueryBuilder('rs')
            ->select('
                rs.reponse,
                COUNT(rs.id) as count,
                ROUND((COUNT(rs.id) * 100.0 / SUM(COUNT(rs.id)) OVER()), 2) as percentage
            ')
            ->where('rs.sondage = :sondageId')
            ->andWhere('rs.reponse IS NOT NULL')
            ->andWhere('rs.reponse != \'\'')
            ->setParameter('sondageId', $sondageId)
            ->groupBy('rs.reponse')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();

        return array_map(function ($row) {
            return [
                'reponse' => $row['reponse'],
                'count' => (int) $row['count'],
                'percentage' => (float) $row['percentage']
            ];
        }, $results);
    }

    /**
     * Trouve les réponses les plus récentes pour un sondage.
     *
     * @param int $sondageId L'ID du sondage
     * @param int $limit Le nombre maximum de réponses à retourner
     * @return ReponseSondage[]
     */
    public function findRecentBySondage(int $sondageId, int $limit = 10): array
    {
        return $this->createQueryBuilder('rs')
            ->innerJoin('rs.sondage', 's')
            ->addSelect('s')
            ->innerJoin('rs.invite', 'i')
            ->addSelect('i')
            ->where('rs.sondage = :sondageId')
            ->setParameter('sondageId', $sondageId)
            ->orderBy('rs.dateReponse', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Vérifie si un invité a déjà répondu à un sondage.
     *
     * @param int $inviteId L'ID de l'invité
     * @param int $sondageId L'ID du sondage
     * @return bool
     */
    public function hasInviteResponded(int $inviteId, int $sondageId): bool
    {
        $count = (int) $this->createQueryBuilder('rs')
            ->select('COUNT(rs.id)')
            ->where('rs.invite = :inviteId')
            ->andWhere('rs.sondage = :sondageId')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('sondageId', $sondageId)
            ->getQuery()
            ->useQueryCache(true)
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve les réponses par période pour analyser les tendances.
     *
     * @param int $sondageId L'ID du sondage
     * @param \DateTimeInterface $startDate Date de début
     * @param \DateTimeInterface $endDate Date de fin
     * @return ReponseSondage[]
     */
    public function findByPeriod(int $sondageId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('rs')
            ->innerJoin('rs.sondage', 's')
            ->addSelect('s')
            ->innerJoin('rs.invite', 'i')
            ->addSelect('i')
            ->where('rs.sondage = :sondageId')
            ->andWhere('rs.dateReponse BETWEEN :startDate AND :endDate')
            ->setParameter('sondageId', $sondageId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('rs.dateReponse', 'ASC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Obtient les statistiques de participation par jour pour un sondage.
     * Utilise les fonctions de fenêtrage PostgreSQL pour optimiser les performances.
     *
     * @param int $sondageId L'ID du sondage
     * @param int $days Nombre de jours à analyser
     * @return array<array{date: string, responses: int, cumulative: int}>
     */
    public function getDailyParticipationStats(int $sondageId, int $days = 30): array
    {
        // Utilisation d'une série de dates PostgreSQL avec LEFT JOIN pour avoir tous les jours
        $sql = "
            WITH date_series AS (
                SELECT generate_series(
                    CURRENT_DATE - INTERVAL '{$days} days',
                    CURRENT_DATE,
                    INTERVAL '1 day'
                )::date AS date
            )
            SELECT
                ds.date::text as date,
                COALESCE(COUNT(rs.id), 0) as responses,
                SUM(COALESCE(COUNT(rs.id), 0)) OVER (ORDER BY ds.date) as cumulative
            FROM date_series ds
            LEFT JOIN reponses_sondages rs ON DATE(rs.date_reponse) = ds.date
                AND rs.sondage_id = :sondageId
            GROUP BY ds.date
            ORDER BY ds.date
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('sondageId', $sondageId);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Recherche dans les réponses par contenu textuel.
     * Utilise la recherche full-text PostgreSQL si disponible.
     *
     * @param int $sondageId L'ID du sondage
     * @param string $searchTerm Le terme de recherche
     * @return ReponseSondage[]
     */
    public function searchInResponses(int $sondageId, string $searchTerm): array
    {
        $qb = $this->createQueryBuilder('rs')
            ->innerJoin('rs.sondage', 's')
            ->addSelect('s')
            ->innerJoin('rs.invite', 'i')
            ->addSelect('i')
            ->where('rs.sondage = :sondageId')
            ->setParameter('sondageId', $sondageId);

        // Utilisation de la recherche full-text PostgreSQL si disponible
        if ($this->supportsFullTextSearch()) {
            $qb->andWhere('to_tsvector(\'french\', rs.reponse) @@ plainto_tsquery(\'french\', :searchTerm)')
                ->setParameter('searchTerm', $searchTerm);
        } else {
            // Fallback avec ILIKE
            $qb->andWhere('rs.reponse ILIKE :searchPattern')
                ->setParameter('searchPattern', '%' . $searchTerm . '%');
        }

        return $qb->orderBy('rs.dateReponse', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Supprime les réponses d'un sondage (utile pour la suppression en cascade).
     *
     * @param int $sondageId L'ID du sondage
     * @return int Le nombre de réponses supprimées
     */
    public function deleteBySondage(int $sondageId): int
    {
        return $this->createQueryBuilder('rs')
            ->delete()
            ->where('rs.sondage = :sondageId')
            ->setParameter('sondageId', $sondageId)
            ->getQuery()
            ->execute();
    }

    /**
     * Met à jour une réponse existante ou en crée une nouvelle.
     *
     * @param int $inviteId L'ID de l'invité
     * @param int $sondageId L'ID du sondage
     * @param string $reponse La nouvelle réponse
     * @return ReponseSondage La réponse mise à jour ou créée
     */
    public function upsertReponse(int $inviteId, int $sondageId, string $reponse): ReponseSondage
    {
        $existingReponse = $this->findByInviteAndSondage($inviteId, $sondageId);

        if ($existingReponse) {
            $existingReponse->setReponse($reponse);
            $existingReponse->setDateReponse(new \DateTime());
            return $existingReponse;
        }

        // Création d'une nouvelle réponse si elle n'existe pas
        $nouvelleReponse = new ReponseSondage();
        $nouvelleReponse->setReponse($reponse);

        // Note: Il faudrait injecter les entités Invite et Sondage
        // Cette méthode nécessiterait une refactorisation pour être complètement fonctionnelle

        return $nouvelleReponse;
    }

    /**
     * Vérifie si la base de données supporte la recherche full-text PostgreSQL.
     */
    private function supportsFullTextSearch(): bool
    {
        try {
            $this->getEntityManager()
                ->getConnection()
                ->executeQuery("SELECT to_tsvector('test')");
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Persiste et flush une nouvelle réponse.
     *
     * @param ReponseSondage $reponse La réponse à sauvegarder
     * @param bool $flush Si true, flush immédiatement
     */
    public function save(ReponseSondage $reponse, bool $flush = false): void
    {
        $this->getEntityManager()->persist($reponse);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une réponse.
     *
     * @param ReponseSondage $reponse La réponse à supprimer
     * @param bool $flush Si true, flush immédiatement
     */
    public function remove(ReponseSondage $reponse, bool $flush = false): void
    {
        $this->getEntityManager()->remove($reponse);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

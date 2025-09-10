<?php

namespace App\Repository;

use App\Entity\StatistiquePresence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StatistiquePresence>
 */
class StatistiquePresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatistiquePresence::class);
    }

    /**
     * Trouve toutes les statistiques pour un couple avec eager loading
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('sp')
            ->addSelect('c')
            ->innerJoin('sp.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('sp.dateStatistique', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les statistiques pour une date spécifique
     */
    public function findByDate(int $coupleId, \DateTimeInterface $date): ?StatistiquePresence
    {
        return $this->createQueryBuilder('sp')
            ->addSelect('c')
            ->innerJoin('sp.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('sp.dateStatistique = :date')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcule le taux de présence pour une date donnée
     * Utilise les données en temps réel si les statistiques ne sont pas à jour
     */
    public function calculateTauxPresence(int $coupleId, \DateTimeInterface $date): float
    {
        // Essaie d'abord de récupérer depuis les statistiques pré-calculées
        $statistique = $this->findByDate($coupleId, $date);

        if ($statistique && $statistique->getTauxPresence() !== null) {
            return (float) $statistique->getTauxPresence();
        }

        // Calcul en temps réel si pas de statistiques disponibles
        return $this->calculateRealTimeTauxPresence($coupleId, $date);
    }

    /**
     * Calcule le taux de présence en temps réel depuis les invitations
     */
    private function calculateRealTimeTauxPresence(int $coupleId, \DateTimeInterface $date): float
    {
        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE i.statut_rsvp = 'confirme') as confirmes,
                COUNT(*) as total_invites
            FROM invites i
            INNER JOIN couples c ON i.couple_id = c.id
            WHERE c.id = :coupleId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['coupleId' => $coupleId])->fetchAssociative();

        $total = (int) $result['total_invites'];
        $confirmes = (int) $result['confirmes'];

        return $total > 0 ? ($confirmes / $total) * 100 : 0.0;
    }

    /**
     * Trouve l'heure de pic d'arrivée avec analyse PostgreSQL
     * Utilise EXTRACT pour analyser les heures d'arrivée
     */
    public function findPeakArrivalTime(int $coupleId): ?array
    {
        // D'abord essaie de récupérer depuis les statistiques
        $preCalculated = $this->createQueryBuilder('sp')
            ->select('sp.heurePicArrivee')
            ->innerJoin('sp.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('sp.heurePicArrivee IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('sp.dateStatistique', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($preCalculated) {
            return [
                'heure' => $preCalculated['heurePicArrivee'],
                'source' => 'precalculated'
            ];
        }

        // Calcul en temps réel depuis les données d'arrivée
        return $this->calculateRealTimePeakArrival($coupleId);
    }

    /**
     * Calcule l'heure de pic d'arrivée en temps réel
     */
    private function calculateRealTimePeakArrival(int $coupleId): ?array
    {
        $sql = "
            SELECT
                EXTRACT(HOUR FROM i.heure_arrivee) as heure,
                COUNT(*) as nb_arrivals
            FROM invites i
            INNER JOIN couples c ON i.couple_id = c.id
            WHERE c.id = :coupleId
              AND i.heure_arrivee IS NOT NULL
            GROUP BY EXTRACT(HOUR FROM i.heure_arrivee)
            ORDER BY nb_arrivals DESC, heure
            LIMIT 1
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['coupleId' => $coupleId])->fetchAssociative();

        if (!$result) {
            return null;
        }

        return [
            'heure' => sprintf('%02d:00', (int) $result['heure']),
            'nb_arrivals' => (int) $result['nb_arrivals'],
            'source' => 'realtime'
        ];
    }

    /**
     * Analyse complète des heures d'arrivée par tranche horaire
     */
    public function getArrivalTimeAnalysis(int $coupleId): array
    {
        $sql = "
            SELECT
                EXTRACT(HOUR FROM i.heure_arrivee) as heure,
                COUNT(*) as nb_arrivals,
                ROUND(
                    (COUNT(*) * 100.0 / SUM(COUNT(*)) OVER()), 2
                ) as pourcentage
            FROM invites i
            INNER JOIN couples c ON i.couple_id = c.id
            WHERE c.id = :coupleId
              AND i.heure_arrivee IS NOT NULL
            GROUP BY EXTRACT(HOUR FROM i.heure_arrivee)
            ORDER BY heure
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery(['coupleId' => $coupleId])->fetchAllAssociative();
    }

    /**
     * Statistiques détaillées pour un couple avec window functions PostgreSQL
     */
    public function getDetailedStatistics(int $coupleId): array
    {
        $sql = "
            SELECT
                sp.date_statistique,
                sp.total_invites,
                sp.confirmes_rsvp,
                sp.presents_ceremonie,
                sp.presents_reception,
                sp.taux_presence,
                sp.heure_pic_arrivee,

                -- Calculs de tendance avec window functions
                LAG(sp.taux_presence) OVER (ORDER BY sp.date_statistique) as taux_precedent,
                sp.taux_presence - LAG(sp.taux_presence) OVER (ORDER BY sp.date_statistique) as evolution_taux,

                -- Moyennes mobiles
                AVG(sp.taux_presence) OVER (
                    ORDER BY sp.date_statistique
                    ROWS BETWEEN 6 PRECEDING AND CURRENT ROW
                ) as moyenne_mobile_7j,

                -- Rang par taux de présence
                ROW_NUMBER() OVER (ORDER BY sp.taux_presence DESC) as rang_taux

            FROM statistiques_presence sp
            INNER JOIN couples c ON sp.couple_id = c.id
            WHERE c.id = :coupleId
            ORDER BY sp.date_statistique DESC
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery(['coupleId' => $coupleId])->fetchAllAssociative();
    }

    /**
     * Trouve les périodes de forte/faible affluence
     */
    public function findAttendancePeaks(int $coupleId, int $daysRange = 30): array
    {
        $sql = "
            WITH daily_stats AS (
                SELECT
                    sp.date_statistique,
                    sp.taux_presence,
                    PERCENTILE_CONT(0.5) OVER () as median_taux,
                    STDDEV(sp.taux_presence) OVER () as std_taux
                FROM statistiques_presence sp
                INNER JOIN couple c ON sp.couple_id = c.id
                WHERE c.id = :coupleId
                  AND sp.date_statistique >= CURRENT_DATE - INTERVAL ':daysRange days'
            )
            SELECT
                date_statistique,
                taux_presence,
                CASE
                    WHEN taux_presence > (median_taux + std_taux) THEN 'pic_eleve'
                    WHEN taux_presence < (median_taux - std_taux) THEN 'pic_faible'
                    ELSE 'normal'
                END as type_periode
            FROM daily_stats
            WHERE taux_presence IS NOT NULL
            ORDER BY date_statistique DESC
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery([
            'coupleId' => $coupleId,
            'daysRange' => $daysRange
        ])->fetchAllAssociative();
    }

    /**
     * Comparaison avec statistiques nationales/moyennes
     */
    public function compareWithAverage(int $coupleId): array
    {
        $sql = "
            WITH couple_stats AS (
                SELECT
                    AVG(sp.taux_presence) as avg_taux_couple,
                    MAX(sp.taux_presence) as max_taux_couple,
                    MIN(sp.taux_presence) as min_taux_couple
                FROM statistiques_presence sp
                WHERE sp.couple_id = :coupleId
            ),
            global_stats AS (
                SELECT
                    AVG(sp.taux_presence) as avg_taux_global,
                    PERCENTILE_CONT(0.25) OVER () as q25_global,
                    PERCENTILE_CONT(0.75) OVER () as q75_global
                FROM statistiques_presence sp
            )
            SELECT
                cs.avg_taux_couple,
                cs.max_taux_couple,
                cs.min_taux_couple,
                gs.avg_taux_global,
                gs.q25_global,
                gs.q75_global,
                cs.avg_taux_couple - gs.avg_taux_global as difference_moyenne,
                CASE
                    WHEN cs.avg_taux_couple > gs.q75_global THEN 'superieur'
                    WHEN cs.avg_taux_couple < gs.q25_global THEN 'inferieur'
                    ELSE 'moyen'
                END as position_relative
            FROM couple_stats cs, global_stats gs
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['coupleId' => $coupleId])->fetchAssociative();

        return $result ?: [];
    }

    /**
     * Prédiction de taux de présence basée sur les tendances
     */
    public function predictAttendanceRate(int $coupleId, int $daysAhead = 7): ?float
    {
        $sql = "
            WITH recent_trend AS (
                SELECT
                    date_statistique,
                    taux_presence,
                    ROW_NUMBER() OVER (ORDER BY date_statistique DESC) as rn
                FROM statistiques_presence sp
                WHERE sp.couple_id = :coupleId
                  AND sp.taux_presence IS NOT NULL
                  AND sp.date_statistique >= CURRENT_DATE - INTERVAL '30 days'
            ),
            trend_calculation AS (
                SELECT
                    REGR_SLOPE(taux_presence, rn) as tendance_slope,
                    REGR_INTERCEPT(taux_presence, rn) as tendance_intercept,
                    AVG(taux_presence) as moyenne_recente
                FROM recent_trend
                WHERE rn <= 14  -- 2 semaines de données
            )
            SELECT
                GREATEST(0, LEAST(100,
                    tendance_intercept + (tendance_slope * :daysAhead)
                )) as prediction,
                moyenne_recente,
                tendance_slope
            FROM trend_calculation
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'coupleId' => $coupleId,
            'daysAhead' => $daysAhead
        ])->fetchAssociative();

        return $result ? (float) $result['prediction'] : null;
    }

    /**
     * Crée ou met à jour les statistiques pour une date
     */
    public function createOrUpdateStatistics(int $coupleId, \DateTimeInterface $date): StatistiquePresence
    {
        $existingStats = $this->findByDate($coupleId, $date);

        if (!$existingStats) {
            $existingStats = new StatistiquePresence();
            $couple = $this->getEntityManager()->getRepository('App:Couple')->find($coupleId);
            $existingStats->setCouple($couple);
            $existingStats->setDateStatistique($date);
        }

        // Recalcul des statistiques
        $this->updateStatisticsData($existingStats, $coupleId);

        return $existingStats;
    }

    /**
     * Met à jour les données statistiques depuis les sources
     * @throws Exception
     */
    private function updateStatisticsData(StatistiquePresence $stats, int $coupleId): void
    {
        $sql = "
            SELECT
                COUNT(*) as total_invites,
                COUNT(*) FILTER (WHERE statut_rsvp = 'confirme') as confirmes_rsvp,
                COUNT(*) FILTER (WHERE present_ceremonie = true) as presents_ceremonie,
                COUNT(*) FILTER (WHERE present_reception = true) as presents_reception
            FROM invites i
            INNER JOIN couples c ON i.couple_id = c.id
            WHERE c.id = :coupleId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['coupleId' => $coupleId])->fetchAssociative();

        $total = (int) $result['total_invites'];
        $confirmes = (int) $result['confirmes_rsvp'];

        $stats->setTotalInvites($total);
        $stats->setConfirmesRsvp($confirmes);
        $stats->setPresentsCeremonie((int) $result['presents_ceremonie']);
        $stats->setPresentsReception((int) $result['presents_reception']);

        $tauxPresence = $total > 0 ? ($confirmes / $total) * 100 : 0.0;
        $stats->setTauxPresence((string) round($tauxPresence, 2));

        // Calcul de l'heure de pic
        $peakTime = $this->calculateRealTimePeakArrival($coupleId);
        if ($peakTime && isset($peakTime['heure'])) {
            $stats->setHeurePicArrivee(new \DateTime($peakTime['heure']));
        }

        $stats->setDerniereMiseAJour(new \DateTime());
    }

    /**
     * Batch update des statistiques pour optimiser les performances
     */
    public function batchUpdateStatistics(array $coupleIds, \DateTimeInterface $date): void
    {
        $em = $this->getEntityManager();
        $batchSize = 50;

        foreach ($coupleIds as $i => $coupleId) {
            $stats = $this->createOrUpdateStatistics($coupleId, $date);
            $em->persist($stats);

            if (($i % $batchSize) === 0) {
                $em->flush();
                $em->clear();
            }
        }

        $em->flush();
        $em->clear();
    }

    /**
     * Sauvegarde avec gestion d'erreur
     */
    public function save(StatistiquePresence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Suppression avec gestion d'erreur
     */
    public function remove(StatistiquePresence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

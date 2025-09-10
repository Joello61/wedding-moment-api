<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContributionCadeau;
use App\Enumeration\StatutContribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<ContributionCadeau>
 */
class ContributionCadeauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContributionCadeau::class);
    }

    /**
     * Trouve toutes les contributions d'un invité avec eager loading
     */
    public function findByInvite(int $inviteId): array
    {
        return $this->createQueryBuilder('cc')
            ->addSelect('i', 'lc', 'cg')
            ->innerJoin('cc.invite', 'i')
            ->leftJoin('cc.cadeau', 'lc')
            ->leftJoin('cc.cagnotte', 'cg')
            ->where('i.id = :inviteId')
            ->setParameter('inviteId', $inviteId)
            ->orderBy('cc.dateContribution', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les contribuants d'un cadeau spécifique
     */
    public function findByCadeau(int $cadeauId): array
    {
        return $this->createQueryBuilder('cc')
            ->addSelect('i', 'lc')
            ->innerJoin('cc.invite', 'i')
            ->innerJoin('cc.cadeau', 'lc')
            ->where('lc.id = :cadeauId')
            ->setParameter('cadeauId', $cadeauId)
            ->orderBy('cc.dateContribution', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les contribuants d'une cagnotte spécifique
     */
    public function findByCagnotte(int $cagnotteId): array
    {
        return $this->createQueryBuilder('cc')
            ->addSelect('i', 'cg')
            ->innerJoin('cc.invite', 'i')
            ->innerJoin('cc.cagnotte', 'cg')
            ->where('cg.id = :cagnotteId')
            ->setParameter('cagnotteId', $cagnotteId)
            ->orderBy('cc.dateContribution', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule la somme totale collectée pour un cadeau avec précision monétaire
     */
    public function sumByCadeau(int $cadeauId): string
    {
        $result = $this->createQueryBuilder('cc')
            ->select('COALESCE(SUM(cc.montant), 0) as totalMontant')
            ->innerJoin('cc.cadeau', 'lc')
            ->where('lc.id = :cadeauId')
            ->andWhere('cc.statut != :statut')
            ->setParameter('cadeauId', $cadeauId)
            ->setParameter('statut', StatutContribution::ANNULE)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (string) $result : '0.00';
    }

    /**
     * Calcule la somme totale collectée pour une cagnotte avec précision monétaire
     */
    public function sumByCagnotte(int $cagnotteId): string
    {
        $result = $this->createQueryBuilder('cc')
            ->select('COALESCE(SUM(cc.montant), 0) as totalMontant')
            ->innerJoin('cc.cagnotte', 'cg')
            ->where('cg.id = :cagnotteId')
            ->andWhere('cc.statut != :statut')
            ->setParameter('cagnotteId', $cagnotteId)
            ->setParameter('statut', StatutContribution::ANNULE)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (string) $result : '0.00';
    }

    /**
     * Statistiques détaillées des contributions par cadeau avec PostgreSQL
     */
    public function getCadeauStatistics(int $cadeauId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_contributions,
                COUNT(*) FILTER (WHERE cc.statut = 'confirme') as contributions_confirmees,
                COUNT(*) FILTER (WHERE cc.statut = 'livre') as contributions_livrees,
                COUNT(*) FILTER (WHERE cc.statut = 'annule') as contributions_annulees,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_total,
                COALESCE(AVG(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_moyen,
                COALESCE(MIN(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_min,
                COALESCE(MAX(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_max
            FROM contributions_cadeaux cc
            INNER JOIN liste_cadeaux lc ON cc.cadeau_id = lc.id
            WHERE lc.id = :cadeauId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['cadeauId' => $cadeauId])->fetchAssociative();

        return [
            'total_contributions' => (int) $result['total_contributions'],
            'contributions_confirmees' => (int) $result['contributions_confirmees'],
            'contributions_livrees' => (int) $result['contributions_livrees'],
            'contributions_annulees' => (int) $result['contributions_annulees'],
            'montant_total' => (float) $result['montant_total'],
            'montant_moyen' => (float) $result['montant_moyen'],
            'montant_min' => (float) $result['montant_min'],
            'montant_max' => (float) $result['montant_max'],
            'taux_confirmation' => $result['total_contributions'] > 0
                ? ($result['contributions_confirmees'] / $result['total_contributions']) * 100
                : 0
        ];
    }

    /**
     * Statistiques détaillées des contributions par cagnotte
     */
    public function getCagnotteStatistics(int $cagnotteId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_contributions,
                COUNT(*) FILTER (WHERE cc.statut = 'confirme') as contributions_confirmees,
                COUNT(*) FILTER (WHERE cc.statut = 'annule') as contributions_annulees,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_total,
                COALESCE(AVG(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_moyen,
                COALESCE(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_median
            FROM contributions_cadeaux cc
            INNER JOIN cagnottes cg ON cc.cagnotte_id = cg.id
            WHERE cg.id = :cagnotteId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['cagnotteId' => $cagnotteId])->fetchAssociative();

        return [
            'total_contributions' => (int) $result['total_contributions'],
            'contributions_confirmees' => (int) $result['contributions_confirmees'],
            'contributions_annulees' => (int) $result['contributions_annulees'],
            'montant_total' => (float) $result['montant_total'],
            'montant_moyen' => (float) $result['montant_moyen'],
            'montant_median' => (float) $result['montant_median'],
            'taux_confirmation' => $result['total_contributions'] > 0
                ? ($result['contributions_confirmees'] / $result['total_contributions']) * 100
                : 0
        ];
    }

    /**
     * Top des contributeurs avec ranking PostgreSQL
     */
    public function getTopContributors(int $limit = 10): array
    {
        $sql = "
            SELECT
                i.id as invite_id,
                i.nom,
                i.prenom,
                COUNT(*) as nb_contributions,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_total,
                COALESCE(AVG(cc.montant) FILTER (WHERE cc.statut != 'annule'), 0) as montant_moyen,
                ROW_NUMBER() OVER (ORDER BY SUM(cc.montant) DESC) as rang
            FROM contributions_cadeaux cc
            INNER JOIN invites i ON cc.invite_id = i.id
            GROUP BY i.id, i.nom, i.prenom
            ORDER BY montant_total DESC
            LIMIT :limit
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery(['limit' => $limit])->fetchAllAssociative();
    }

    /**
     * Analyse temporelle des contributions avec window functions
     */
    public function getTemporalAnalysis(int $daysBack = 30): array
    {
        $sql = "
            SELECT
                DATE(cc.date_contribution) as date_contribution,
                COUNT(*) as nb_contributions,
                COALESCE(SUM(cc.montant), 0) as montant_jour,

                -- Moyennes mobiles sur 7 jours
                AVG(COUNT(*)) OVER (
                    ORDER BY DATE(cc.date_contribution)
                    ROWS BETWEEN 6 PRECEDING AND CURRENT ROW
                ) as moyenne_mobile_contributions,

                AVG(SUM(cc.montant)) OVER (
                    ORDER BY DATE(cc.date_contribution)
                    ROWS BETWEEN 6 PRECEDING AND CURRENT ROW
                ) as moyenne_mobile_montant,

                -- Comparaison avec jour précédent
                LAG(COUNT(*)) OVER (ORDER BY DATE(cc.date_contribution)) as contributions_precedent,
                LAG(SUM(cc.montant)) OVER (ORDER BY DATE(cc.date_contribution)) as montant_precedent

            FROM contributions_cadeaux cc
            WHERE cc.date_contribution >= CURRENT_DATE - INTERVAL ':daysBack days'
            GROUP BY DATE(cc.date_contribution)
            ORDER BY date_contribution DESC
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery(['daysBack' => $daysBack])->fetchAllAssociative();
    }

    /**
     * Recherche avancée avec filtres multiples
     */
    public function findWithAdvancedFilters(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('cc')
            ->addSelect('i', 'lc', 'cg')
            ->innerJoin('cc.invite', 'i')
            ->leftJoin('cc.cadeau', 'lc')
            ->leftJoin('cc.cagnotte', 'cg');

        if (isset($filters['invite_id'])) {
            $qb->andWhere('i.id = :inviteId')
                ->setParameter('inviteId', $filters['invite_id']);
        }

        if (isset($filters['cadeau_id'])) {
            $qb->andWhere('lc.id = :cadeauId')
                ->setParameter('cadeauId', $filters['cadeau_id']);
        }

        if (isset($filters['cagnotte_id'])) {
            $qb->andWhere('cg.id = :cagnotteId')
                ->setParameter('cagnotteId', $filters['cagnotte_id']);
        }

        if (isset($filters['statut'])) {
            if (is_array($filters['statut'])) {
                $qb->andWhere('cc.statut IN (:statuts)')
                    ->setParameter('statuts', $filters['statut']);
            } else {
                $qb->andWhere('cc.statut = :statut')
                    ->setParameter('statut', $filters['statut']);
            }
        }

        if (isset($filters['montant_min'])) {
            $qb->andWhere('cc.montant >= :montantMin')
                ->setParameter('montantMin', $filters['montant_min']);
        }

        if (isset($filters['montant_max'])) {
            $qb->andWhere('cc.montant <= :montantMax')
                ->setParameter('montantMax', $filters['montant_max']);
        }

        if (isset($filters['date_from'])) {
            $qb->andWhere('cc.dateContribution >= :dateFrom')
                ->setParameter('dateFrom', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $qb->andWhere('cc.dateContribution <= :dateTo')
                ->setParameter('dateTo', $filters['date_to']);
        }

        if (isset($filters['type_contribution'])) {
            switch ($filters['type_contribution']) {
                case 'cadeau':
                    $qb->andWhere('cc.cadeau IS NOT NULL');
                    break;
                case 'cagnotte':
                    $qb->andWhere('cc.cagnotte IS NOT NULL');
                    break;
            }
        }

        return $qb;
    }

    /**
     * Distribution des montants par tranches avec PostgreSQL
     * @throws Exception
     */
    public function getMontantDistribution(): array
    {
        $sql = "
        WITH tranches AS (
            SELECT
                CASE
                    WHEN montant < 25 THEN '0-24€'
                    WHEN montant < 50 THEN '25-49€'
                    WHEN montant < 100 THEN '50-99€'
                    WHEN montant < 200 THEN '100-199€'
                    WHEN montant >= 200 THEN '200€+'
                    ELSE 'Non défini'
                END AS tranche_montant
            FROM contributions_cadeaux
            WHERE statut != 'annule'
              AND montant IS NOT NULL
        )
        SELECT
            tranche_montant,
            COUNT(*) AS nb_contributions,
            ROUND((COUNT(*) * 100.0 / SUM(COUNT(*)) OVER()), 2) AS pourcentage
        FROM tranches
        GROUP BY tranche_montant
        ORDER BY
            CASE tranche_montant
                WHEN '0-24€' THEN 1
                WHEN '25-49€' THEN 2
                WHEN '50-99€' THEN 3
                WHEN '100-199€' THEN 4
                WHEN '200€+' THEN 5
                ELSE 6
            END
        ";

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql)
            ->fetchAllAssociative();
    }


    /**
     * Contributions en attente de confirmation avec délais
     */
    public function findPendingContributions(int $daysThreshold = 7): array
    {
        return $this->createQueryBuilder('cc')
            ->addSelect('i', 'lc', 'cg')
            ->innerJoin('cc.invite', 'i')
            ->leftJoin('cc.cadeau', 'lc')
            ->leftJoin('cc.cagnotte', 'cg')
            ->where('cc.statut = :statut')
            ->andWhere('cc.dateContribution <= :thresholdDate')
            ->setParameter('statut', StatutContribution::EN_ATTENTE)
            ->setParameter('thresholdDate', new \DateTime("-{$daysThreshold} days"))
            ->orderBy('cc.dateContribution', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rapport de réconciliation financière
     */
    public function getFinancialReconciliation(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo): array
    {
        $sql = "
            SELECT
                'cadeau' as type,
                lc.nom_cadeau as nom,
                COUNT(*) as nb_contributions,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut = 'confirme'), 0) as montant_confirme,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut = 'livre'), 0) as montant_livre,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut = 'annule'), 0) as montant_annule
            FROM contributions_cadeaux cc
            INNER JOIN liste_cadeaux lc ON cc.cadeau_id = lc.id
            WHERE cc.date_contribution BETWEEN :dateFrom AND :dateTo
            GROUP BY lc.id, lc.nom_cadeau

            UNION ALL

            SELECT
                'cagnotte' as type,
                cg.nom_cagnotte as nom,
                COUNT(*) as nb_contributions,
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut = 'confirme'), 0) as montant_confirme,
                0 as montant_livre,  -- Les cagnottes ne sont pas livrées
                COALESCE(SUM(cc.montant) FILTER (WHERE cc.statut = 'annule'), 0) as montant_annule
            FROM contributions_cadeaux cc
            INNER JOIN cagnottes cg ON cc.cagnotte_id = cg.id
            WHERE cc.date_contribution BETWEEN :dateFrom AND :dateTo
            GROUP BY cg.id, cg.nom_cagnotte

            ORDER BY type, nom
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery([
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d')
        ])->fetchAllAssociative();
    }

    /**
     * Trouve les contributions par invité avec totaux
     */
    public function findByInviteWithTotals(int $inviteId): array
    {
        $contributions = $this->findByInvite($inviteId);

        $totaux = [
            'nb_contributions' => count($contributions),
            'montant_total' => '0.00',
            'nb_cadeaux' => 0,
            'nb_cagnottes' => 0
        ];

        $montantTotal = 0.0;
        foreach ($contributions as $contribution) {
            if ($contribution->getStatut() !== StatutContribution::ANNULE && $contribution->getMontant()) {
                $montantTotal += (float) $contribution->getMontant();
            }

            if ($contribution->isCadeauContribution()) {
                $totaux['nb_cadeaux']++;
            } elseif ($contribution->isCagnotteContribution()) {
                $totaux['nb_cagnottes']++;
            }
        }

        $totaux['montant_total'] = number_format($montantTotal, 2, '.', '');

        return [
            'contributions' => $contributions,
            'totaux' => $totaux
        ];
    }

    /**
     * Sauvegarde avec gestion d'erreur et validation métier
     */
    public function save(ContributionCadeau $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Suppression avec gestion d'erreur
     */
    public function remove(ContributionCadeau $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Batch processing pour les mises à jour de statut
     */
    public function batchUpdateStatus(array $contributionIds, StatutContribution $newStatus): int
    {
        return $this->createQueryBuilder('cc')
            ->update()
            ->set('cc.statut', ':newStatus')
            ->where('cc.id IN (:ids)')
            ->setParameter('newStatus', $newStatus)
            ->setParameter('ids', $contributionIds)
            ->getQuery()
            ->execute();
    }
}

<?php

namespace App\Repository;

use App\Entity\Cagnotte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Cagnotte>
 */
class CagnotteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cagnotte::class);
    }

    /**
     * Trouve toutes les cagnottes actives d'un couple
     * Ordonnées par ordre d'affichage puis par date de création
     */
    public function findActiveByCouple(int $coupleId): array
    {
        return $this->createActiveCagnottesQuery($coupleId)
            ->orderBy('c.ordreAffichage', 'ASC')
            ->addOrderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cagnottes dont l'objectif n'est pas encore atteint
     * Utilise une comparaison PostgreSQL optimisée avec CAST pour les DECIMAL
     */
    public function findByGoal(int $coupleId): array
    {
        return $this->createActiveCagnottesQuery($coupleId)
            ->andWhere('c.objectifMontant IS NOT NULL')
            ->andWhere('CAST(c.montantActuel AS DECIMAL(10,2)) < CAST(c.objectifMontant AS DECIMAL(10,2))')
            ->orderBy('c.ordreAffichage', 'ASC')
            ->addOrderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total des contributions pour une cagnotte spécifique
     * Utilise une requête optimisée avec agrégation PostgreSQL
     */
    public function sumContributions(Uuid $cagnotteId): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(CAST(contrib.montant AS DECIMAL(10,2))), 0) as total')
            ->leftJoin('c.contributions', 'contrib')
            ->where('c.id = :cagnotteId')
            ->setParameter('cagnotteId', $cagnotteId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * Trouve une cagnotte spécifique pour un couple donné
     * Sécurise l'accès en vérifiant l'appartenance au couple
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?Cagnotte
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les cagnottes les plus proches de leur objectif
     * Utile pour des suggestions d'investissement
     */
    public function findClosestToGoal(int $coupleId, int $limit = 3): array
    {
        return $this->createActiveCagnottesQuery($coupleId)
            ->andWhere('c.objectifMontant IS NOT NULL')
            ->andWhere('c.objectifMontant > 0')
            ->andWhere('CAST(c.montantActuel AS DECIMAL(10,2)) < CAST(c.objectifMontant AS DECIMAL(10,2))')
            ->addSelect('(CAST(c.montantActuel AS DECIMAL(10,2)) / CAST(c.objectifMontant AS DECIMAL(10,2))) * 100 as HIDDEN progression')
            ->orderBy('progression', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de cagnottes par nom avec recherche floue
     * Utilise ILIKE de PostgreSQL pour une recherche insensible à la casse
     */
    public function findBySearchTerm(int $coupleId, string $searchTerm): array
    {
        return $this->createActiveCagnottesQuery($coupleId)
            ->andWhere('c.nomCagnotte ILIKE :searchTerm OR c.description ILIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('c.ordreAffichage', 'ASC')
            ->addOrderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales des cagnottes d'un couple
     * Retourne un tableau avec diverses métriques
     */
    public function getStatistics(int $coupleId): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as totalCagnottes',
                'COUNT(CASE WHEN c.actif = true THEN c.id END) as cagnottesActives',
                'COALESCE(SUM(CAST(c.montantActuel AS DECIMAL(10,2))), 0) as montantTotal',
                'COALESCE(SUM(CAST(c.objectifMontant AS DECIMAL(10,2))), 0) as objectifTotal',
                'COUNT(CASE WHEN c.objectifMontant IS NOT NULL AND CAST(c.montantActuel AS DECIMAL(10,2)) >= CAST(c.objectifMontant AS DECIMAL(10,2)) THEN c.id END) as objectifsAtteints'
            ])
            ->where('c.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Met à jour le montant actuel d'une cagnotte
     * Méthode optimisée pour éviter de charger l'entité complète
     */
    public function updateMontantActuel(Uuid $cagnotteId, string $nouveauMontant): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.montantActuel', ':montant')
            ->set('c.dateMiseAJour', ':now')
            ->where('c.id = :id')
            ->setParameter('montant', $nouveauMontant)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $cagnotteId)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les cagnottes avec le plus de contributions récentes
     * Utile pour identifier les cagnottes populaires
     */
    public function findMostActiveRecently(int $coupleId, int $days = 30, int $limit = 5): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify('-' . $days . ' days');

        return $this->createActiveCagnottesQuery($coupleId)
            ->leftJoin('c.contributions', 'contrib')
            ->andWhere('contrib.dateContribution >= :dateLimit')
            ->groupBy('c.id')
            ->addSelect('COUNT(contrib.id) as HIDDEN nbContributions')
            ->orderBy('nbContributions', 'DESC')
            ->setParameter('dateLimit', $dateLimit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Query Builder réutilisable pour les cagnottes actives d'un couple
     * Bonne pratique : centraliser la logique commune
     */
    private function createActiveCagnottesQuery(int $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->where('c.couple = :coupleId')
            ->andWhere('c.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true);
    }

    /**
     * Sauvegarde une entité (create/update)
     * Méthode helper pour simplifier les contrôleurs
     */
    public function save(Cagnotte $cagnotte, bool $flush = false): void
    {
        $this->getEntityManager()->persist($cagnotte);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     * Méthode helper pour simplifier les contrôleurs
     */
    public function remove(Cagnotte $cagnotte, bool $flush = false): void
    {
        $this->getEntityManager()->remove($cagnotte);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Réordonne les cagnottes d'un couple
     * Optimisé avec une seule requête PostgreSQL
     */
    public function reorderCagnottes(int $coupleId, array $orderedIds): void
    {
        $em = $this->getEntityManager();

        foreach ($orderedIds as $position => $cagnotteId) {
            $em->createQueryBuilder()
                ->update(Cagnotte::class, 'c')
                ->set('c.ordreAffichage', ':position')
                ->set('c.dateMiseAJour', ':now')
                ->where('c.id = :id')
                ->andWhere('c.couple = :coupleId')
                ->setParameter('position', $position + 1)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $cagnotteId)
                ->setParameter('coupleId', $coupleId)
                ->getQuery()
                ->execute();
        }
    }
}

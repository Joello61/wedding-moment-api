<?php

namespace App\Repository;

use App\Entity\ListeCadeau;
use App\Enumeration\PrioriteCadeau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Repository pour la gestion des listes de cadeaux.
 *
 * @extends ServiceEntityRepository<ListeCadeau>
 */
class ListeCadeauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListeCadeau::class);
    }

    /**
     * Trouve tous les cadeaux d'un couple.
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('lc.ordreAffichage', 'ASC')
            ->addOrderBy('lc.nomCadeau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve uniquement les cadeaux actifs d'un couple.
     */
    public function findActive(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('lc.ordreAffichage', 'ASC')
            ->addOrderBy('lc.nomCadeau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux d'un couple filtrés par catégorie.
     */
    public function findByCategory(int $coupleId, string $categorie): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.categorie = :categorie')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('categorie', $categorie)
            ->setParameter('actif', true)
            ->orderBy('lc.ordreAffichage', 'ASC')
            ->addOrderBy('lc.nomCadeau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux en attente (quantité souhaitée non atteinte).
     */
    public function findPending(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->andWhere('lc.quantiteRecue < lc.quantiteSouhaitee')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('lc.priorite', 'DESC')
            ->addOrderBy('lc.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un cadeau spécifique d'un couple (sécurité multi-couple).
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?ListeCadeau
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.id = :id')
            ->andWhere('lc.couple = :coupleId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les cadeaux par priorité.
     */
    public function findByPriority(int $coupleId, PrioriteCadeau $priorite): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.priorite = :priorite')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('priorite', $priorite)
            ->setParameter('actif', true)
            ->orderBy('lc.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux complets (quantité souhaitée atteinte).
     */
    public function findCompleted(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->andWhere('lc.quantiteRecue >= lc.quantiteSouhaitee')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('lc.dateMiseAJour', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux avec leurs contributions (optimisé).
     */
    public function findWithContributions(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->leftJoin('lc.contributions', 'c')
            ->addSelect('c')
            ->leftJoin('c.inviteContribuant', 'i')
            ->addSelect('i')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('lc.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle dans les cadeaux.
     */
    public function searchByName(int $coupleId, string $searchTerm): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->andWhere('LOWER(lc.nomCadeau) LIKE LOWER(:searchTerm) OR LOWER(lc.description) LIKE LOWER(:searchTerm)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('lc.nomCadeau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux dans une fourchette de prix.
     */
    public function findByPriceRange(int $coupleId, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $qb = $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true);

        if ($minPrice !== null) {
            $qb->andWhere('CAST(lc.prixEstime AS DECIMAL) >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('CAST(lc.prixEstime AS DECIMAL) <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        return $qb->orderBy('CAST(lc.prixEstime AS DECIMAL)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux les plus populaires (avec le plus de contributions).
     */
    public function findMostPopular(int $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('lc')
            ->select('lc', 'COUNT(c.id) as contributionCount')
            ->leftJoin('lc.contributions', 'c')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('lc.id')
            ->orderBy('contributionCount', 'DESC')
            ->addOrderBy('lc.nomCadeau', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cadeaux avec pagination et filtres.
     */
    public function findWithFilters(
        int $coupleId,
        ?string $categorie = null,
        ?PrioriteCadeau $priorite = null,
        ?bool $pending = null,
        int $limit = 10,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true);

        if ($categorie !== null) {
            $qb->andWhere('lc.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        if ($priorite !== null) {
            $qb->andWhere('lc.priorite = :priorite')
                ->setParameter('priorite', $priorite);
        }

        if ($pending === true) {
            $qb->andWhere('lc.quantiteRecue < lc.quantiteSouhaitee');
        } elseif ($pending === false) {
            $qb->andWhere('lc.quantiteRecue >= lc.quantiteSouhaitee');
        }

        return $qb->orderBy('lc.priorite', 'DESC')
            ->addOrderBy('lc.ordreAffichage', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les cadeaux avec des filtres.
     */
    public function countWithFilters(
        int $coupleId,
        ?string $categorie = null,
        ?PrioriteCadeau $priorite = null,
        ?bool $pending = null
    ): int {
        $qb = $this->createQueryBuilder('lc')
            ->select('COUNT(lc.id)')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true);

        if ($categorie !== null) {
            $qb->andWhere('lc.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        if ($priorite !== null) {
            $qb->andWhere('lc.priorite = :priorite')
                ->setParameter('priorite', $priorite);
        }

        if ($pending === true) {
            $qb->andWhere('lc.quantiteRecue < lc.quantiteSouhaitee');
        } elseif ($pending === false) {
            $qb->andWhere('lc.quantiteRecue >= lc.quantiteSouhaitee');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques globales des cadeaux pour un couple.
     */
    public function getStats(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->select([
                'COUNT(lc.id) as totalCadeaux',
                'SUM(CASE WHEN lc.quantiteRecue >= lc.quantiteSouhaitee THEN 1 ELSE 0 END) as cadeauxComplets',
                'SUM(CASE WHEN lc.quantiteRecue < lc.quantiteSouhaitee THEN 1 ELSE 0 END) as cadeauxEnAttente',
                'SUM(CAST(lc.prixEstime AS DECIMAL)) as valeurTotaleEstimee',
                'AVG(CAST(lc.prixEstime AS DECIMAL)) as prixMoyen'
            ])
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Statistiques par catégorie.
     */
    public function getStatsByCategory(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->select([
                'lc.categorie',
                'COUNT(lc.id) as count',
                'SUM(CASE WHEN lc.quantiteRecue >= lc.quantiteSouhaitee THEN 1 ELSE 0 END) as completed',
                'SUM(CAST(lc.prixEstime AS DECIMAL)) as totalValue'
            ])
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->andWhere('lc.categorie IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('lc.categorie')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour la quantité reçue d'un cadeau.
     */
    public function updateQuantiteRecue(Uuid $cadeauId, int $nouvelleQuantite): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(ListeCadeau::class, 'lc')
            ->set('lc.quantiteRecue', ':quantite')
            ->set('lc.dateMiseAJour', ':now')
            ->where('lc.id = :id')
            ->setParameter('quantite', $nouvelleQuantite)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $cadeauId, 'uuid')
            ->getQuery()
            ->execute();
    }

    /**
     * Met à jour l'ordre d'affichage des cadeaux (batch update).
     */
    public function updateDisplayOrder(array $cadeauOrders): int
    {
        $updated = 0;
        $em = $this->getEntityManager();

        foreach ($cadeauOrders as $cadeauId => $order) {
            $updated += $em->createQueryBuilder()
                ->update(ListeCadeau::class, 'lc')
                ->set('lc.ordreAffichage', ':order')
                ->set('lc.dateMiseAJour', ':now')
                ->where('lc.id = :id')
                ->setParameter('order', $order)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $cadeauId, 'uuid')
                ->getQuery()
                ->execute();
        }

        return $updated;
    }

    /**
     * Trouve les cadeaux récemment ajoutés.
     */
    public function findRecent(int $coupleId, int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->andWhere('lc.dateCreation >= :since')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('since', $since)
            ->orderBy('lc.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories utilisées par un couple.
     */
    public function findUsedCategories(int $coupleId): array
    {
        return $this->createQueryBuilder('lc')
            ->select('DISTINCT lc.categorie')
            ->andWhere('lc.couple = :coupleId')
            ->andWhere('lc.actif = :actif')
            ->andWhere('lc.categorie IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('lc.categorie', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * QueryBuilder de base pour les requêtes complexes.
     */
    public function createBaseQueryBuilder(int $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);
    }

    /**
     * Sauvegarde une entité ListeCadeau.
     */
    public function save(ListeCadeau $listeCadeau, bool $flush = false): void
    {
        $this->getEntityManager()->persist($listeCadeau);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité ListeCadeau.
     */
    public function remove(ListeCadeau $listeCadeau, bool $flush = false): void
    {
        $this->getEntityManager()->remove($listeCadeau);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

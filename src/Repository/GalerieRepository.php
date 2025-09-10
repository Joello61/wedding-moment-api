<?php

namespace App\Repository;

use App\Entity\Galerie;
use App\Enumeration\TypeGalerie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Repository pour la gestion des galeries.
 *
 * @extends ServiceEntityRepository<Galerie>
 */
class GalerieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Galerie::class);
    }

    /**
     * Trouve toutes les galeries d'un couple.
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->addOrderBy('g.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les galeries d'un couple filtrées par type.
     */
    public function findByType(int $coupleId, TypeGalerie $type): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.type = :type')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('type', $type)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->addOrderBy('g.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve uniquement les galeries actives d'un couple.
     */
    public function findActive(int $coupleId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->addOrderBy('g.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une galerie spécifique d'un couple (sécurité multi-couple).
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?Galerie
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.id = :id')
            ->andWhere('g.couple = :coupleId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les galeries d'un couple triées par ordre d'affichage.
     */
    public function findOrdered(int $coupleId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->addOrderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les galeries actives avec comptage des médias (optimisé pour PostgreSQL).
     */
    public function findActiveWithMediaCount(int $coupleId): array
    {
        return $this->createQueryBuilder('g')
            ->select('g', 'COUNT(m.id) as mediaCount')
            ->leftJoin('g.medias', 'm')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('g.id')
            ->orderBy('g.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les galeries par type avec pagination optimisée.
     */
    public function findByTypeWithPagination(int $coupleId, TypeGalerie $type, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.type = :type')
            ->andWhere('g.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->addOrderBy('g.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de galeries par type pour un couple.
     */
    public function countByType(int $coupleId, TypeGalerie $type): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.type = :type')
            ->andWhere('g.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche textuelle dans les galeries (optimisé PostgreSQL avec ILIKE).
     */
    public function searchByName(int $coupleId, string $searchTerm): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.actif = :actif')
            ->andWhere('LOWER(g.nom) LIKE LOWER(:searchTerm) OR LOWER(g.description) LIKE LOWER(:searchTerm)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la galerie suivante selon l'ordre d'affichage.
     */
    public function findNextInOrder(int $coupleId, ?int $currentOrder): ?Galerie
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->setMaxResults(1);

        if ($currentOrder !== null) {
            $qb->andWhere('g.ordreAffichage > :currentOrder')
                ->setParameter('currentOrder', $currentOrder);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Trouve la galerie précédente selon l'ordre d'affichage.
     */
    public function findPreviousInOrder(int $coupleId, ?int $currentOrder): ?Galerie
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('g.ordreAffichage', 'DESC')
            ->setMaxResults(1);

        if ($currentOrder !== null) {
            $qb->andWhere('g.ordreAffichage < :currentOrder')
                ->setParameter('currentOrder', $currentOrder);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Met à jour l'ordre d'affichage des galeries (batch update optimisé).
     */
    public function updateDisplayOrder(array $galerieOrders): int
    {
        $updated = 0;
        $em = $this->getEntityManager();

        foreach ($galerieOrders as $galerieId => $order) {
            $updated += $em->createQueryBuilder()
                ->update(Galerie::class, 'g')
                ->set('g.ordreAffichage', ':order')
                ->set('g.dateMiseAJour', ':now')
                ->where('g.id = :id')
                ->setParameter('order', $order)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $galerieId, 'uuid')
                ->getQuery()
                ->execute();
        }

        return $updated;
    }

    /**
     * Trouve les galeries récemment mises à jour.
     */
    public function findRecentlyUpdated(int $coupleId, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->andWhere('g.dateMiseAJour >= :since')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('since', $since)
            ->orderBy('g.dateMiseAJour', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par type de galerie pour un couple.
     */
    public function getStatsByType(int $coupleId): array
    {
        return $this->createQueryBuilder('g')
            ->select('g.type, COUNT(g.id) as count, SUM(CASE WHEN g.actif = true THEN 1 ELSE 0 END) as activeCount')
            ->andWhere('g.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('g.type')
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder de base pour les requêtes complexes.
     */
    public function createBaseQueryBuilder(int $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);
    }

    /**
     * Sauvegarde une entité Galerie.
     */
    public function save(Galerie $galerie, bool $flush = false): void
    {
        $this->getEntityManager()->persist($galerie);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité Galerie.
     */
    public function remove(Galerie $galerie, bool $flush = false): void
    {
        $this->getEntityManager()->remove($galerie);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Optimisation : précharge les relations nécessaires.
     */
    public function findWithMedias(int $coupleId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.medias', 'm')
            ->addSelect('m')
            ->leftJoin('g.couple', 'c')
            ->addSelect('c')
            ->andWhere('g.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('g.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

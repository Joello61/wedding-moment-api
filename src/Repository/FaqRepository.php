<?php

namespace App\Repository;

use App\Entity\Faq;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception as DBALException;

/**
 * @extends ServiceEntityRepository<Faq>
 */
class FaqRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faq::class);
    }

    public function save(Faq $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Faq $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les FAQ d'un couple.
     * Optimisé avec join pour éviter les requêtes N+1.
     *
     * @return Faq[]
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('f.ordreAffichage', 'ASC')
            ->addOrderBy('f.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtre les FAQ par catégorie pour un couple.
     * Optimisé avec index composite.
     *
     * @return Faq[]
     */
    public function findByCategory(int $coupleId, string $categorie): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('f.categorie = :categorie')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('categorie', $categorie)
            ->orderBy('f.ordreAffichage', 'ASC')
            ->addOrderBy('f.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * FAQ triées par ordre d'affichage pour un couple.
     * Gère les valeurs NULL en les plaçant à la fin.
     *
     * @return Faq[]
     */
    public function findOrdered(int $coupleId): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('f.ordreAffichage', 'ASC NULLS LAST')
            ->addOrderBy('f.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une FAQ spécifique par ID et couple.
     * Sécurise l'accès en vérifiant l'appartenance au couple.
     */
    public function findByIdAndCouple(int $id, int $coupleId): ?Faq
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('f.id = :id')
            ->andWhere('c.id = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * FAQ actives uniquement pour un couple.
     * Utilise l'index composite pour optimiser la performance.
     *
     * @return Faq[]
     */
    public function findActive(int $coupleId): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('f.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('f.ordreAffichage', 'ASC NULLS LAST')
            ->addOrderBy('f.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * FAQ actives par catégorie pour un couple.
     * Combine les filtres actif et catégorie avec optimisation.
     *
     * @return Faq[]
     */
    public function findActiveByCategory(int $coupleId, string $categorie): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('f.categorie = :categorie')
            ->andWhere('f.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('categorie', $categorie)
            ->setParameter('actif', true)
            ->orderBy('f.ordreAffichage', 'ASC NULLS LAST')
            ->addOrderBy('f.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle dans les questions et réponses.
     * Utilise PostgreSQL ILIKE pour une recherche insensible à la casse.
     *
     * @return Faq[]
     */
    public function searchByText(int $coupleId, string $searchTerm, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('(f.question ILIKE :searchTerm OR f.reponse ILIKE :searchTerm)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('f.ordreAffichage', 'ASC NULLS LAST')
            ->addOrderBy('f.dateCreation', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('f.actif = :actif')
                ->setParameter('actif', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les catégories utilisées par un couple.
     * Utile pour construire les filtres dynamiquement.
     *
     * @return string[]
     */
    public function findCategoriesByCouple(int $coupleId, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('DISTINCT f.categorie')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('f.categorie IS NOT NULL')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('f.categorie', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('f.actif = :actif')
                ->setParameter('actif', true);
        }

        $result = $qb->getQuery()->getArrayResult();
        return array_filter(array_column($result, 'categorie'));
    }

    /**
     * Compte les FAQ par catégorie pour un couple.
     * Statistiques pour l'interface d'administration.
     */
    public function countByCategory(int $coupleId, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.categorie as categorie', 'COUNT(f.id) as total')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('f.categorie')
            ->orderBy('total', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('f.actif = :actif')
                ->setParameter('actif', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Réorganise l'ordre d'affichage des FAQ.
     * Met à jour plusieurs FAQ en une seule transaction.
     */
    public function updateDisplayOrder(array $faqOrders): bool
    {
        try {
            $em = $this->getEntityManager();
            $em->beginTransaction();

            foreach ($faqOrders as $faqId => $order) {
                $em->createQuery('
                    UPDATE App\Entity\Faq f
                    SET f.ordreAffichage = :order, f.dateMiseAJour = :now
                    WHERE f.id = :faqId
                ')
                    ->setParameter('order', $order)
                    ->setParameter('now', new \DateTime())
                    ->setParameter('faqId', $faqId)
                    ->execute();
            }

            $em->commit();
            return true;
        } catch (DBALException $e) {
            $this->getEntityManager()->rollback();
            throw new DBALException('Erreur lors de la mise à jour de l\'ordre d\'affichage: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Trouve le prochain ordre d'affichage disponible pour un couple.
     * Utile pour ajouter de nouvelles FAQ à la fin.
     */
    public function getNextDisplayOrder(int $coupleId, ?string $categorie = null): int
    {
        $qb = $this->createQueryBuilder('f')
            ->select('MAX(f.ordreAffichage) as maxOrder')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($categorie !== null) {
            $qb->andWhere('f.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return ($result ?? 0) + 1;
    }

    /**
     * Duplique une FAQ pour un autre couple.
     * Utile pour les templates ou FAQ communes.
     */
    public function duplicateFaq(int $sourceFaqId, int $targetCoupleId): ?Faq
    {
        $sourceFaq = $this->find($sourceFaqId);
        if (!$sourceFaq) {
            return null;
        }

        $newFaq = new Faq();
        $newFaq->setQuestion($sourceFaq->getQuestion());
        $newFaq->setReponse($sourceFaq->getReponse());
        $newFaq->setCategorie($sourceFaq->getCategorie());
        $newFaq->setActif($sourceFaq->isActif());
        $newFaq->setOrdreAffichage($this->getNextDisplayOrder($targetCoupleId, $sourceFaq->getCategorie()));

        // Assuming you have a method to get Couple entity
        $couple = $this->getEntityManager()->getReference('App\Entity\Couple', $targetCoupleId);
        $newFaq->setCouple($couple);

        $this->save($newFaq, true);

        return $newFaq;
    }

    /**
     * Active/désactive plusieurs FAQ simultanément.
     */
    public function toggleActiveStatus(array $faqIds, bool $active): int
    {
        return $this->getEntityManager()
            ->createQuery('
                UPDATE App\Entity\Faq f
                SET f.actif = :active, f.dateMiseAJour = :now
                WHERE f.id IN (:faqIds)
            ')
            ->setParameter('active', $active)
            ->setParameter('now', new \DateTime())
            ->setParameter('faqIds', $faqIds)
            ->execute();
    }

    /**
     * Recherche avancée avec critères multiples.
     */
    public function findByCriteria(
        ?int $coupleId = null,
        ?string $categorie = null,
        ?bool $actif = null,
        ?string $searchTerm = null,
        ?\DateTime $dateFrom = null,
        ?\DateTime $dateTo = null,
        ?array $orderBy = ['ordreAffichage' => 'ASC'],
        int $limit = 50,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($coupleId !== null) {
            $qb->andWhere('c.id = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        if ($categorie !== null) {
            $qb->andWhere('f.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        if ($actif !== null) {
            $qb->andWhere('f.actif = :actif')
                ->setParameter('actif', $actif);
        }

        if ($searchTerm !== null) {
            $qb->andWhere('(f.question ILIKE :searchTerm OR f.reponse ILIKE :searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        if ($dateFrom !== null) {
            $qb->andWhere('f.dateCreation >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('f.dateCreation <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        // Gestion de l'ordre
        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $orderClause = $field === 'ordreAffichage' ? 'f.ordreAffichage ' . $direction . ' NULLS LAST' : 'f.' . $field;
                $qb->addOrderBy($orderClause, $direction);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques complètes pour un couple.
     */
    public function getStatistics(int $coupleId): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'categories' => 0,
            'by_category' => []
        ];

        // Total et statut
        $generalStats = $this->createQueryBuilder('f')
            ->select('COUNT(f.id) as total', 'SUM(CASE WHEN f.actif = true THEN 1 ELSE 0 END) as active')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleResult();

        $stats['total'] = (int) $generalStats['total'];
        $stats['active'] = (int) $generalStats['active'];
        $stats['inactive'] = $stats['total'] - $stats['active'];

        // Par catégorie
        $stats['by_category'] = $this->countByCategory($coupleId, false);
        $stats['categories'] = count(array_filter(array_column($stats['by_category'], 'categorie')));

        return $stats;
    }

    /**
     * Trouve les FAQ les plus récemment modifiées.
     */
    public function findRecentlyModified(int $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'c')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('f.dateMiseAJour', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Optimise l'ordre d'affichage en supprimant les trous.
     * Recalcule l'ordre séquentiellement.
     */
    public function optimizeDisplayOrder(int $coupleId, ?string $categorie = null): int
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.id')
            ->innerJoin('f.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('f.ordreAffichage', 'ASC NULLS LAST')
            ->addOrderBy('f.dateCreation', 'ASC');

        if ($categorie !== null) {
            $qb->andWhere('f.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $faqIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        $updatedCount = 0;
        foreach ($faqIds as $index => $faqId) {
            $newOrder = $index + 1;
            $result = $this->getEntityManager()
                ->createQuery('UPDATE App\Entity\Faq f SET f.ordreAffichage = :order WHERE f.id = :id')
                ->setParameter('order', $newOrder)
                ->setParameter('id', $faqId)
                ->execute();
            $updatedCount += $result;
        }

        return $updatedCount;
    }
}

<?php

namespace App\Repository;

use App\Entity\Media;
use App\Enumeration\TypeMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des médias (photos et vidéos).
 *
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Trouve tous les médias d'une galerie.
     */
    public function findByGalerie(int $galerieId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId)
            ->orderBy('m.ordreAffichage', 'ASC')
            ->addOrderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias uploadés par un invité.
     */
    public function findByInvite(int $inviteId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.invite = :inviteId')
            ->setParameter('inviteId', $inviteId)
            ->orderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias d'une galerie filtrés par type.
     */
    public function findByType(int $galerieId, TypeMedia $type): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.typeMedia = :type')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('type', $type)
            ->orderBy('m.ordreAffichage', 'ASC')
            ->addOrderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve uniquement les médias approuvés d'une galerie.
     */
    public function findApproved(int $galerieId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.approuve = :approuve')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('approuve', true)
            ->orderBy('m.ordreAffichage', 'ASC')
            ->addOrderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par tags JSONB (optimisé PostgreSQL).
     */
    public function searchByTag(int $galerieId, string $tag): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('JSON_CONTAINS(m.tags, :tag) = 1 OR m.tags @> :tagJson')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('tag', json_encode([$tag]))
            ->setParameter('tagJson', json_encode([$tag]))
            ->orderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par tags JSONB (version PostgreSQL native).
     */
    public function searchByTagPostgreSQL(int $galerieId, string $tag): array
    {
        $sql = '
            SELECT m.* FROM medias m
            WHERE m.galerie_id = :galerieId
            AND m.tags ? :tag
            ORDER BY m.ordre_affichage NULLS LAST, m.date_creation DESC
        ';

        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addEntityResult(Media::class, 'm');
        $rsm->addFieldResult('m', 'id', 'id');
        $rsm->addFieldResult('m', 'galerie_id', 'galerie');
        $rsm->addFieldResult('m', 'invite_id', 'invite');
        $rsm->addFieldResult('m', 'nom_fichier', 'nomFichier');
        $rsm->addFieldResult('m', 'url_fichier', 'urlFichier');
        $rsm->addFieldResult('m', 'url_miniature', 'urlMiniature');
        $rsm->addFieldResult('m', 'type_media', 'typeMedia');
        $rsm->addFieldResult('m', 'taille_fichier', 'tailleFichier');
        $rsm->addFieldResult('m', 'format', 'format');
        $rsm->addFieldResult('m', 'largeur', 'largeur');
        $rsm->addFieldResult('m', 'hauteur', 'hauteur');
        $rsm->addFieldResult('m', 'duree', 'duree');
        $rsm->addFieldResult('m', 'description', 'description');
        $rsm->addFieldResult('m', 'tags', 'tags');
        $rsm->addFieldResult('m', 'approuve', 'approuve');
        $rsm->addFieldResult('m', 'ordre_affichage', 'ordreAffichage');
        $rsm->addFieldResult('m', 'date_creation', 'dateCreation');

        return $this->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->setParameter('galerieId', $galerieId)
            ->setParameter('tag', $tag)
            ->getResult();
    }

    /**
     * Trouve les médias d'une galerie triés par ordre d'affichage.
     */
    public function findOrdered(int $galerieId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId)
            ->orderBy('m.ordreAffichage', 'ASC')
            ->addOrderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par multiple tags avec opérateur AND/OR.
     */
    public function searchByMultipleTags(int $galerieId, array $tags, string $operator = 'OR'): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId);

        if ($operator === 'AND') {
            // Tous les tags doivent être présents
            foreach ($tags as $index => $tag) {
                $qb->andWhere("JSON_EXTRACT(m.tags, '$[*]') LIKE :tag{$index}")
                    ->setParameter("tag{$index}", "%{$tag}%");
            }
        } else {
            // Au moins un tag doit être présent
            $orConditions = [];
            foreach ($tags as $index => $tag) {
                $orConditions[] = "JSON_EXTRACT(m.tags, '$[*]') LIKE :tag{$index}";
                $qb->setParameter("tag{$index}", "%{$tag}%");
            }
            $qb->andWhere(implode(' OR ', $orConditions));
        }

        return $qb->orderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias avec pagination et filtres.
     */
    public function findWithFilters(
        int $galerieId,
        ?TypeMedia $type = null,
        ?bool $approved = null,
        ?int $inviteId = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId);

        if ($type !== null) {
            $qb->andWhere('m.typeMedia = :type')
                ->setParameter('type', $type);
        }

        if ($approved !== null) {
            $qb->andWhere('m.approuve = :approved')
                ->setParameter('approved', $approved);
        }

        if ($inviteId !== null) {
            $qb->andWhere('m.invite = :inviteId')
                ->setParameter('inviteId', $inviteId);
        }

        return $qb->orderBy('m.ordreAffichage', 'ASC')
            ->addOrderBy('m.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias récents d'une galerie.
     */
    public function findRecent(int $galerieId, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.approuve = :approuve')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('approuve', true)
            ->orderBy('m.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias par format (jpg, mp4, etc.).
     */
    public function findByFormat(int $galerieId, string $format): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('LOWER(m.format) = LOWER(:format)')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('format', $format)
            ->orderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias volumineux (au-dessus d'une taille donnée).
     */
    public function findLargeFiles(int $galerieId, int $minSizeInBytes): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.tailleFichier >= :minSize')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('minSize', $minSizeInBytes)
            ->orderBy('m.tailleFichier', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias par dimensions (pour les photos).
     */
    public function findByDimensions(int $galerieId, int $minWidth = null, int $minHeight = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.typeMedia = :photoType')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('photoType', TypeMedia::PHOTO);

        if ($minWidth !== null) {
            $qb->andWhere('m.largeur >= :minWidth')
                ->setParameter('minWidth', $minWidth);
        }

        if ($minHeight !== null) {
            $qb->andWhere('m.hauteur >= :minHeight')
                ->setParameter('minHeight', $minHeight);
        }

        return $qb->orderBy('m.largeur', 'DESC')
            ->addOrderBy('m.hauteur', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les médias en attente d'approbation.
     */
    public function findPendingApproval(int $galerieId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.approuve = :approved')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('approved', false)
            ->orderBy('m.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des médias par galerie.
     */
    public function getStats(int $galerieId): array
    {
        return $this->createQueryBuilder('m')
            ->select([
                'COUNT(m.id) as totalMedias',
                'SUM(CASE WHEN m.typeMedia = :photoType THEN 1 ELSE 0 END) as totalPhotos',
                'SUM(CASE WHEN m.typeMedia = :videoType THEN 1 ELSE 0 END) as totalVideos',
                'SUM(CASE WHEN m.approuve = true THEN 1 ELSE 0 END) as approvedMedias',
                'SUM(CASE WHEN m.approuve = false THEN 1 ELSE 0 END) as pendingMedias',
                'SUM(m.tailleFichier) as totalSize',
                'AVG(m.tailleFichier) as avgSize'
            ])
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('photoType', TypeMedia::PHOTO)
            ->setParameter('videoType', TypeMedia::VIDEO)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les tags utilisés dans une galerie.
     * @throws Exception
     */
    public function findUsedTags(int $galerieId): array
    {
        $sql = '
            SELECT DISTINCT jsonb_array_elements_text(tags) as tag
            FROM medias
            WHERE galerie_id = :galerieId
            AND tags IS NOT NULL
            ORDER BY tag
        ';

        return $this->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn($sql, ['galerieId' => $galerieId]);
    }

    /**
     * Met à jour l'ordre d'affichage des médias (batch update).
     */
    public function updateDisplayOrder(array $mediaOrders): int
    {
        $updated = 0;
        $em = $this->getEntityManager();

        foreach ($mediaOrders as $mediaId => $order) {
            $updated += $em->createQueryBuilder()
                ->update(Media::class, 'm')
                ->set('m.ordreAffichage', ':order')
                ->where('m.id = :id')
                ->setParameter('order', $order)
                ->setParameter('id', $mediaId, 'uuid')
                ->getQuery()
                ->execute();
        }

        return $updated;
    }

    /**
     * Met à jour le statut d'approbation de plusieurs médias.
     */
    public function updateApprovalStatus(array $mediaIds, bool $approved): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(Media::class, 'm')
            ->set('m.approuve', ':approved')
            ->where('m.id IN (:ids)')
            ->setParameter('approved', $approved)
            ->setParameter('ids', $mediaIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les médias par lot (avec nettoyage des fichiers).
     */
    public function deleteMultiple(array $mediaIds): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->delete(Media::class, 'm')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $mediaIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les médias dupliqués (même nom de fichier).
     */
    public function findDuplicates(int $galerieId): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.nomFichier', 'COUNT(m.id) as count')
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId)
            ->groupBy('m.nomFichier')
            ->having('COUNT(m.id) > 1')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle dans les descriptions et noms de fichiers.
     */
    public function searchInContent(int $galerieId, string $searchTerm): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere(
                'LOWER(m.nomFichier) LIKE LOWER(:searchTerm) OR ' .
                'LOWER(m.description) LIKE LOWER(:searchTerm)'
            )
            ->setParameter('galerieId', $galerieId)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('m.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le média suivant dans l'ordre d'affichage.
     */
    public function findNext(int $galerieId, ?int $currentOrder): ?Media
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.approuve = :approved')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('approved', true)
            ->orderBy('m.ordreAffichage', 'ASC')
            ->addOrderBy('m.dateCreation', 'ASC')
            ->setMaxResults(1);

        if ($currentOrder !== null) {
            $qb->andWhere('m.ordreAffichage > :currentOrder')
                ->setParameter('currentOrder', $currentOrder);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Trouve le média précédent dans l'ordre d'affichage.
     */
    public function findPrevious(int $galerieId, ?int $currentOrder): ?Media
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->andWhere('m.approuve = :approved')
            ->setParameter('galerieId', $galerieId)
            ->setParameter('approved', true)
            ->orderBy('m.ordreAffichage', 'DESC')
            ->addOrderBy('m.dateCreation', 'DESC')
            ->setMaxResults(1);

        if ($currentOrder !== null) {
            $qb->andWhere('m.ordreAffichage < :currentOrder')
                ->setParameter('currentOrder', $currentOrder);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * QueryBuilder de base pour les requêtes complexes.
     */
    public function createBaseQueryBuilder(int $galerieId): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.galerie = :galerieId')
            ->setParameter('galerieId', $galerieId);
    }

    /**
     * Sauvegarde une entité Media.
     */
    public function save(Media $media, bool $flush = false): void
    {
        $this->getEntityManager()->persist($media);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité Media.
     */
    public function remove(Media $media, bool $flush = false): void
    {
        $this->getEntityManager()->remove($media);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

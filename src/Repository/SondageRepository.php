<?php

namespace App\Repository;

use App\Entity\Sondage;
use App\Enumeration\TypeSondage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sondage>
 */
class SondageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sondage::class);
    }

    /**
     * Trouve tous les sondages actifs d'un couple.
     *
     * @param int $coupleId L'ID du couple
     * @return Sondage[]
     */
    public function findActiveByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->where('s.couple = :coupleId')
            ->andWhere('s.actif = :actif')
            ->andWhere('(s.dateFin IS NULL OR s.dateFin > :now)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.ordreAffichage', 'ASC')
            ->addOrderBy('s.dateCreation', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Filtre les sondages par type pour un couple donné.
     *
     * @param int $coupleId L'ID du couple
     * @param TypeSondage $type Le type de sondage
     * @return Sondage[]
     */
    public function findByType(int $coupleId, TypeSondage $type): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->where('s.couple = :coupleId')
            ->andWhere('s.typeSondage = :type')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('type', $type)
            ->orderBy('s.ordreAffichage', 'ASC')
            ->addOrderBy('s.dateCreation', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Trouve les derniers sondages créés (global ou par couple).
     *
     * @param int $limit Le nombre maximum de sondages à retourner
     * @param int|null $coupleId L'ID du couple (optionnel)
     * @return Sondage[]
     */
    public function findRecent(int $limit = 5, ?int $coupleId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->orderBy('s.dateCreation', 'DESC')
            ->setMaxResults($limit);

        if ($coupleId !== null) {
            $qb->where('s.couple = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        return $qb->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Trouve un sondage spécifique avec vérification de sécurité multi-couple.
     *
     * @param int $id L'ID du sondage
     * @param int $coupleId L'ID du couple
     * @return Sondage|null
     */
    public function findByIdAndCouple(int $id, int $coupleId): ?Sondage
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->leftJoin('s.reponses', 'r')
            ->addSelect('r')
            ->leftJoin('r.invite', 'i')
            ->addSelect('i')
            ->where('s.id = :id')
            ->andWhere('s.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    /**
     * Recherche dans les sondages par titre ou description avec support JSONB.
     * Utilise la recherche full-text PostgreSQL et les capacités JSONB.
     *
     * @param int $coupleId L'ID du couple
     * @param string $searchTerm Le terme de recherche
     * @return Sondage[]
     */
    public function searchSondages(int $coupleId, string $searchTerm): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->where('s.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        if ($this->supportsFullTextSearch()) {
            // Recherche full-text dans titre, description et options JSON
            $qb->andWhere('(
                to_tsvector(\'french\', s.titre) @@ plainto_tsquery(\'french\', :searchTerm) OR
                to_tsvector(\'french\', COALESCE(s.description, \'\')) @@ plainto_tsquery(\'french\', :searchTerm) OR
                s.optionsJson::text ILIKE :searchPattern
            )')
                ->setParameter('searchTerm', $searchTerm)
                ->setParameter('searchPattern', '%' . $searchTerm . '%');
        } else {
            // Fallback avec recherche basique
            $searchPattern = '%' . strtolower($searchTerm) . '%';
            $qb->andWhere('(
                LOWER(s.titre) LIKE :searchPattern OR
                LOWER(s.description) LIKE :searchPattern OR
                LOWER(s.optionsJson::text) LIKE :searchPattern
            )')
                ->setParameter('searchPattern', $searchPattern);
        }

        return $qb->orderBy('s.dateCreation', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Trouve les sondages avec des options spécifiques en utilisant les opérateurs JSONB.
     *
     * @param int $coupleId L'ID du couple
     * @param string $optionKey La clé de l'option à rechercher
     * @param mixed $optionValue La valeur de l'option (optionnel)
     * @return Sondage[]
     */
    public function findByJsonOption(int $coupleId, string $optionKey, mixed $optionValue = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->where('s.couple = :coupleId')
            ->andWhere('s.optionsJson ? :optionKey')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('optionKey', $optionKey);

        if ($optionValue !== null) {
            // Utilisation des opérateurs JSONB pour la recherche précise
            $qb->andWhere('s.optionsJson ->> :optionKey = :optionValue')
                ->setParameter('optionValue', (string) $optionValue);
        }

        return $qb->orderBy('s.dateCreation', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Obtient les statistiques des sondages pour un couple.
     *
     * @param int $coupleId L'ID du couple
     * @return array{total: int, actifs: int, par_type: array, avec_reponses: int}
     */
    public function getStatsForCouple(int $coupleId): array
    {
        // Statistiques de base en une requête
        $baseStats = $this->createQueryBuilder('s')
            ->select('
                COUNT(s.id) as total,
                COUNT(CASE WHEN s.actif = true AND (s.dateFin IS NULL OR s.dateFin > :now) THEN 1 END) as actifs,
                COUNT(CASE WHEN SIZE(s.reponses) > 0 THEN 1 END) as avec_reponses
            ')
            ->leftJoin('s.reponses', 'r')
            ->where('s.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->useQueryCache(true)
            ->getSingleResult();

        // Statistiques par type
        $statsByType = $this->createQueryBuilder('s')
            ->select('s.typeSondage, COUNT(s.id) as count')
            ->where('s.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('s.typeSondage')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();

        $parType = [];
        foreach ($statsByType as $stat) {
            $parType[$stat['typeSondage']->value] = (int) $stat['count'];
        }

        return [
            'total' => (int) $baseStats['total'],
            'actifs' => (int) $baseStats['actifs'],
            'avec_reponses' => (int) $baseStats['avec_reponses'],
            'par_type' => $parType
        ];
    }

    /**
     * Trouve les sondages expirés qui doivent être désactivés.
     *
     * @return Sondage[]
     */
    public function findExpiredSondages(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.actif = true')
            ->andWhere('s.dateFin IS NOT NULL')
            ->andWhere('s.dateFin <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour l'ordre d'affichage des sondages d'un couple.
     *
     * @param int $coupleId L'ID du couple
     * @param array<int, int> $ordres Tableau [sondageId => ordre]
     * @return int Le nombre de sondages mis à jour
     */
    public function updateOrdresAffichage(int $coupleId, array $ordres): int
    {
        $updated = 0;

        foreach ($ordres as $sondageId => $ordre) {
            $updated += $this->createQueryBuilder('s')
                ->update()
                ->set('s.ordreAffichage', ':ordre')
                ->set('s.dateMiseAJour', ':now')
                ->where('s.id = :sondageId')
                ->andWhere('s.couple = :coupleId')
                ->setParameter('ordre', $ordre)
                ->setParameter('sondageId', $sondageId)
                ->setParameter('coupleId', $coupleId)
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->execute();
        }

        return $updated;
    }

    /**
     * Clone un sondage avec ses options JSON.
     *
     * @param int $originalId L'ID du sondage original
     * @param int $coupleId L'ID du couple destination
     * @param string $nouveauTitre Le nouveau titre
     * @return Sondage|null Le nouveau sondage cloné
     */
    public function cloneSondage(int $originalId, int $coupleId, string $nouveauTitre): ?Sondage
    {
        $original = $this->findByIdAndCouple($originalId, $coupleId);

        if (!$original) {
            return null;
        }

        $clone = new Sondage();
        $clone->setTitre($nouveauTitre);
        $clone->setDescription($original->getDescription());
        $clone->setTypeSondage($original->getTypeSondage());
        $clone->setOptionsJson($original->getOptionsJson()); // Copie du JSONB
        $clone->setCouple($original->getCouple());
        $clone->setActif(false); // Désactivé par défaut

        return $clone;
    }

    /**
     * Analyse les options JSON les plus utilisées.
     * Utilise les fonctions d'agrégation JSONB de PostgreSQL.
     *
     * @param int $coupleId L'ID du couple
     * @return array<array{key: string, usage_count: int}>
     */
    public function analyzeJsonOptions(int $coupleId): array
    {
        // Requête SQL native pour exploiter les capacités JSONB
        $sql = "
            SELECT
                jsonb_object_keys(options_json) as key,
                COUNT(*) as usage_count
            FROM sondages s
            WHERE s.couple_id = :coupleId
                AND s.options_json IS NOT NULL
            GROUP BY jsonb_object_keys(options_json)
            ORDER BY usage_count DESC
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('coupleId', $coupleId);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Trouve les sondages par plage de dates avec optimisation.
     *
     * @param int $coupleId L'ID du couple
     * @param \DateTimeInterface $startDate Date de début
     * @param \DateTimeInterface $endDate Date de fin
     * @param bool $activeOnly Si true, ne retourne que les sondages actifs
     * @return Sondage[]
     */
    public function findByDateRange(
        int $coupleId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        bool $activeOnly = false
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.couple', 'c')
            ->addSelect('c')
            ->where('s.couple = :coupleId')
            ->andWhere('s.dateCreation BETWEEN :startDate AND :endDate')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($activeOnly) {
            $qb->andWhere('s.actif = true')
                ->andWhere('(s.dateFin IS NULL OR s.dateFin > :now)')
                ->setParameter('now', new \DateTime());
        }

        return $qb->orderBy('s.dateCreation', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Désactive automatiquement les sondages expirés.
     *
     * @return int Le nombre de sondages désactivés
     */
    public function deactivateExpiredSondages(): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.actif', ':inactive')
            ->set('s.dateMiseAJour', ':now')
            ->where('s.actif = true')
            ->andWhere('s.dateFin IS NOT NULL')
            ->andWhere('s.dateFin <= :now')
            ->setParameter('inactive', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
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
     * Persiste et flush un nouveau sondage.
     *
     * @param Sondage $sondage Le sondage à sauvegarder
     * @param bool $flush Si true, flush immédiatement
     */
    public function save(Sondage $sondage, bool $flush = false): void
    {
        $this->getEntityManager()->persist($sondage);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un sondage.
     *
     * @param Sondage $sondage Le sondage à supprimer
     * @param bool $flush Si true, flush immédiatement
     */
    public function remove(Sondage $sondage, bool $flush = false): void
    {
        $this->getEntityManager()->remove($sondage);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

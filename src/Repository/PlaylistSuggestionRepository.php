<?php

namespace App\Repository;

use App\Entity\PlaylistSuggestion;
use App\Enumeration\StatutSuggestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des suggestions de playlist.
 *
 * @extends ServiceEntityRepository<PlaylistSuggestion>
 */
class PlaylistSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistSuggestion::class);
    }

    /**
     * Trouve toutes les suggestions d'un couple, filtrées par statut.
     *
     * @param int $coupleId L'ID du couple
     * @param StatutSuggestion|null $statut Le statut à filtrer (optionnel)
     * @return PlaylistSuggestion[]
     */
    public function findByCouple(int $coupleId, ?StatutSuggestion $statut = null): array
    {
        $qb = $this->createQueryBuilder('ps')
            ->innerJoin('ps.couple', 'c')
            ->addSelect('c') // Évite le lazy loading
            ->innerJoin('ps.invite', 'i')
            ->addSelect('i') // Évite le lazy loading
            ->where('ps.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('ps.dateSuggestion', 'DESC');

        if ($statut !== null) {
            $qb->andWhere('ps.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Trouve les suggestions en attente pour un couple.
     *
     * @param int $coupleId L'ID du couple
     * @return PlaylistSuggestion[]
     */
    public function findPendingByCouple(int $coupleId): array
    {
        return $this->findByCouple($coupleId, StatutSuggestion::EN_ATTENTE);
    }

    /**
     * Recherche par titre ou artiste avec support de recherche full-text PostgreSQL.
     *
     * @param int $coupleId L'ID du couple
     * @param string $query La requête de recherche
     * @return PlaylistSuggestion[]
     */
    public function searchByTitleOrArtist(int $coupleId, string $query): array
    {
        // Utilisation de la recherche full-text PostgreSQL pour de meilleures performances
        $qb = $this->createQueryBuilder('ps')
            ->innerJoin('ps.couple', 'c')
            ->addSelect('c')
            ->innerJoin('ps.invite', 'i')
            ->addSelect('i')
            ->where('ps.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        // Si PostgreSQL avec support de recherche full-text
        if ($this->supportsFullTextSearch()) {
            $qb->andWhere('(
                to_tsvector(\'french\', ps.titreChanson) @@ plainto_tsquery(\'french\', :query) OR
                to_tsvector(\'french\', ps.artiste) @@ plainto_tsquery(\'french\', :query)
            )')
                ->setParameter('query', $query);
        } else {
            // Fallback pour recherche basique avec ILIKE (optimisé PostgreSQL)
            $searchTerm = '%' . strtolower($query) . '%';
            $qb->andWhere('(
                LOWER(ps.titreChanson) LIKE :searchTerm OR
                LOWER(ps.artiste) LIKE :searchTerm
            )')
                ->setParameter('searchTerm', $searchTerm);
        }

        return $qb->orderBy('ps.dateSuggestion', 'DESC')
            ->setMaxResults(50) // Limite raisonnable pour les performances
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Compte le nombre de suggestions par statut pour un couple.
     *
     * @param int $coupleId L'ID du couple
     * @param StatutSuggestion $statut Le statut à compter
     * @return int Le nombre de suggestions
     */
    public function countByStatus(int $coupleId, StatutSuggestion $statut): int
    {
        return (int) $this->createQueryBuilder('ps')
            ->select('COUNT(ps.id)')
            ->where('ps.couple = :coupleId')
            ->andWhere('ps.statut = :statut')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->useQueryCache(true)
            ->getSingleScalarResult();
    }

    /**
     * Récupère les statistiques complètes pour le dashboard d'un couple.
     *
     * @param int $coupleId L'ID du couple
     * @return array{en_attente: int, approuvee: int, refusee: int, total: int}
     */
    public function getStatsForCouple(int $coupleId): array
    {
        $result = $this->createQueryBuilder('ps')
            ->select('
                ps.statut,
                COUNT(ps.id) as count
            ')
            ->where('ps.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('ps.statut')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();

        // Initialisation avec des valeurs par défaut
        $stats = [
            'en_attente' => 0,
            'approuvee' => 0,
            'refusee' => 0,
            'total' => 0
        ];

        foreach ($result as $row) {
            $statut = $row['statut']->value; // Récupération de la valeur de l'enum
            $count = (int) $row['count'];
            $stats[$statut] = $count;
            $stats['total'] += $count;
        }

        return $stats;
    }

    /**
     * Trouve les suggestions récentes pour un couple (utile pour l'activité récente).
     *
     * @param int $coupleId L'ID du couple
     * @param int $limit Le nombre maximum de suggestions à retourner
     * @return PlaylistSuggestion[]
     */
    public function findRecentByCouple(int $coupleId, int $limit = 10): array
    {
        return $this->createQueryBuilder('ps')
            ->innerJoin('ps.couple', 'c')
            ->addSelect('c')
            ->innerJoin('ps.invite', 'i')
            ->addSelect('i')
            ->where('ps.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('ps.dateSuggestion', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Trouve les suggestions par genre musical pour un couple.
     *
     * @param int $coupleId L'ID du couple
     * @param string $genre Le genre musical
     * @return PlaylistSuggestion[]
     */
    public function findByGenre(int $coupleId, string $genre): array
    {
        return $this->createQueryBuilder('ps')
            ->innerJoin('ps.couple', 'c')
            ->addSelect('c')
            ->innerJoin('ps.invite', 'i')
            ->addSelect('i')
            ->where('ps.couple = :coupleId')
            ->andWhere('LOWER(ps.genre) = LOWER(:genre)')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('genre', $genre)
            ->orderBy('ps.dateSuggestion', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Met à jour le statut de plusieurs suggestions en une seule requête.
     *
     * @param array<int> $suggestionIds Les IDs des suggestions à mettre à jour
     * @param StatutSuggestion $nouveauStatut Le nouveau statut
     * @return int Le nombre de lignes affectées
     */
    public function updateStatusBatch(array $suggestionIds, StatutSuggestion $nouveauStatut): int
    {
        if (empty($suggestionIds)) {
            return 0;
        }

        return $this->createQueryBuilder('ps')
            ->update()
            ->set('ps.statut', ':statut')
            ->where('ps.id IN (:ids)')
            ->setParameter('statut', $nouveauStatut)
            ->setParameter('ids', $suggestionIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les genres les plus populaires pour un couple.
     *
     * @param int $coupleId L'ID du couple
     * @param int $limit Le nombre maximum de genres à retourner
     * @return array<array{genre: string, count: int}>
     */
    public function findPopularGenres(int $coupleId, int $limit = 10): array
    {
        return $this->createQueryBuilder('ps')
            ->select('ps.genre, COUNT(ps.id) as count')
            ->where('ps.couple = :coupleId')
            ->andWhere('ps.genre IS NOT NULL')
            ->andWhere('ps.genre != \'\'')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('ps.genre')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * Vérifie si la base de données supporte la recherche full-text PostgreSQL.
     */
    private function supportsFullTextSearch(): bool
    {
        try {
            // Test simple pour vérifier si les fonctions de recherche full-text sont disponibles
            $this->getEntityManager()
                ->getConnection()
                ->executeQuery("SELECT to_tsvector('test')");
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Créer un QueryBuilder réutilisable avec les jointures de base.
     */
    private function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('ps')
            ->innerJoin('ps.couple', 'c')
            ->addSelect('c')
            ->innerJoin('ps.invite', 'i')
            ->addSelect('i');
    }

    /**
     * Persiste et flush une nouvelle suggestion.
     *
     * @param PlaylistSuggestion $suggestion La suggestion à sauvegarder
     * @param bool $flush Si true, flush immédiatement
     */
    public function save(PlaylistSuggestion $suggestion, bool $flush = false): void
    {
        $this->getEntityManager()->persist($suggestion);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une suggestion.
     *
     * @param PlaylistSuggestion $suggestion La suggestion à supprimer
     * @param bool $flush Si true, flush immédiatement
     */
    public function remove(PlaylistSuggestion $suggestion, bool $flush = false): void
    {
        $this->getEntityManager()->remove($suggestion);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

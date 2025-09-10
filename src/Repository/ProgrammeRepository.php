<?php

namespace App\Repository;

use App\Entity\Programme;
use App\Enumeration\TypeActivite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Programme>
 */
class ProgrammeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Programme::class);
    }

    /**
     * Trouve toutes les activités programmées d'un couple
     * Inclut les activités actives et inactives
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve uniquement les activités actives d'un couple
     * Pour affichage public aux invités
     */
    public function findActiveByCouple(int $coupleId): array
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->orderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtre les activités par type pour un couple donné
     * Types : ceremonie, cocktail, diner, danse, etc.
     */
    public function findByType(int $coupleId, TypeActivite $typeActivite): array
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->andWhere('p.typeActivite = :typeActivite')
            ->setParameter('typeActivite', $typeActivite)
            ->orderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les activités ordonnées pour affichage
     * Tri optimal par ordre d'affichage puis heure de début
     */
    public function findOrdered(int $coupleId): array
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->orderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->addOrderBy('p.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une activité spécifique pour un couple donné
     * Sécurise l'accès en vérifiant l'appartenance au couple
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?Programme
    {
        return $this->createQueryBuilder('p')
            ->where('p.id = :id')
            ->andWhere('p.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les activités par tranche horaire
     * Utile pour planning détaillé et gestion des conflits
     */
    public function findByTimeRange(
        int $coupleId,
        \DateTimeInterface $heureDebut,
        \DateTimeInterface $heureFin
    ): array {
        return $this->createActiveProgrammeQuery($coupleId)
            ->andWhere('p.heureDebut >= :heureDebut')
            ->andWhere('p.heureDebut <= :heureFin OR p.heureFin <= :heureFin')
            ->setParameter('heureDebut', $heureDebut)
            ->setParameter('heureFin', $heureFin)
            ->orderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Groupe les activités par type
     * Retourne un tableau associatif [type => [programmes...]]
     */
    public function findGroupedByType(int $coupleId): array
    {
        $programmes = $this->createActiveProgrammeQuery($coupleId)
            ->orderBy('p.typeActivite', 'ASC')
            ->addOrderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($programmes as $programme) {
            $type = $programme->getTypeActivite()?->value ?: 'autres';
            $grouped[$type][] = $programme;
        }

        return $grouped;
    }

    /**
     * Trouve les activités par lieu
     * Utile pour organisation logistique
     */
    public function findByLieu(int $coupleId, string $lieu): array
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->andWhere('p.lieu ILIKE :lieu')
            ->setParameter('lieu', '%' . $lieu . '%')
            ->orderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détecte les conflits d'horaires potentiels
     * Vérifie les chevauchements d'activités
     */
    public function findTimeConflicts(int $coupleId): array
    {
        $programmes = $this->findActiveByCouple($coupleId);
        $conflicts = [];

        for ($i = 0; $i < count($programmes); $i++) {
            for ($j = $i + 1; $j < count($programmes); $j++) {
                $prog1 = $programmes[$i];
                $prog2 = $programmes[$j];

                if ($this->hasTimeOverlap($prog1, $prog2)) {
                    $conflicts[] = [
                        'programme1' => $prog1,
                        'programme2' => $prog2,
                        'type_conflit' => 'chevauchement_horaire'
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Recherche dans les titres et descriptions
     * Utilise ILIKE de PostgreSQL pour recherche insensible à la casse
     */
    public function findBySearchTerm(int $coupleId, string $searchTerm): array
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->andWhere('p.titre ILIKE :searchTerm OR p.description ILIKE :searchTerm OR p.lieu ILIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('p.ordreAffichage', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques du programme par type d'activité
     */
    public function getStatisticsByType(int $coupleId): array
    {
        $results = $this->createActiveProgrammeQuery($coupleId)
            ->select([
                'p.typeActivite',
                'COUNT(p.id) as nb_activites',
                'MIN(p.heureDebut) as premiere_heure',
                'MAX(COALESCE(p.heureFin, p.heureDebut)) as derniere_heure'
            ])
            ->groupBy('p.typeActivite')
            ->orderBy('p.typeActivite', 'ASC')
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($results as $result) {
            $type = $result['typeActivite']?->value ?: 'non_defini';
            $formatted[$type] = [
                'nb_activites' => (int) $result['nb_activites'],
                'premiere_heure' => $result['premiere_heure']?->format('H:i'),
                'derniere_heure' => $result['derniere_heure']?->format('H:i')
            ];
        }

        return $formatted;
    }

    /**
     * Durée totale estimée du programme
     * Calcule la durée entre la première et dernière activité
     */
    public function getTotalDuration(int $coupleId): ?array
    {
        $result = $this->createActiveProgrammeQuery($coupleId)
            ->select([
                'MIN(p.heureDebut) as premiere_activite',
                'MAX(COALESCE(p.heureFin, p.heureDebut)) as derniere_activite'
            ])
            ->getQuery()
            ->getSingleResult();

        if (!$result['premiere_activite'] || !$result['derniere_activite']) {
            return null;
        }

        $debut = $result['premiere_activite'];
        $fin = $result['derniere_activite'];
        $interval = $debut->diff($fin);

        return [
            'heure_debut' => $debut->format('H:i'),
            'heure_fin' => $fin->format('H:i'),
            'duree_totale' => sprintf('%dh%02d', $interval->h, $interval->i),
            'minutes_totales' => ($interval->h * 60) + $interval->i
        ];
    }

    /**
     * Trouve la prochaine activité à partir d'une heure donnée
     * Utile pour affichage "En cours" / "À venir"
     */
    public function findNextActivity(int $coupleId, \DateTimeInterface $currentTime): ?Programme
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->andWhere('p.heureDebut >= :currentTime')
            ->setParameter('currentTime', $currentTime)
            ->orderBy('p.heureDebut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve l'activité en cours à une heure donnée
     * Pour affichage temps réel le jour J
     */
    public function findCurrentActivity(int $coupleId, \DateTimeInterface $currentTime): ?Programme
    {
        return $this->createActiveProgrammeQuery($coupleId)
            ->andWhere('p.heureDebut <= :currentTime')
            ->andWhere('p.heureFin >= :currentTime OR p.heureFin IS NULL')
            ->setParameter('currentTime', $currentTime)
            ->orderBy('p.heureDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve la prochaine position pour l'ordre d'affichage
     * Évite les conflits lors de l'ajout de nouvelles activités
     */
    public function getNextOrdreAffichage(int $coupleId): int
    {
        $maxOrdre = $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.ordreAffichage), 0)')
            ->where('p.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $maxOrdre) + 1;
    }

    /**
     * Réordonne les activités d'un couple
     * Optimisé avec des requêtes UPDATE en batch
     */
    public function reorderProgrammes(int $coupleId, array $orderedIds): void
    {
        $em = $this->getEntityManager();

        foreach ($orderedIds as $position => $programmeId) {
            $em->createQueryBuilder()
                ->update(Programme::class, 'p')
                ->set('p.ordreAffichage', ':position')
                ->set('p.dateMiseAJour', ':now')
                ->where('p.id = :id')
                ->andWhere('p.couple = :coupleId')
                ->setParameter('position', $position + 1)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $programmeId)
                ->setParameter('coupleId', $coupleId)
                ->getQuery()
                ->execute();
        }
    }

    /**
     * Active/désactive une activité
     * Méthode optimisée sans chargement de l'entité
     */
    public function toggleActive(Uuid $programmeId, int $coupleId, bool $actif = null): bool
    {
        $em = $this->getEntityManager();

        // Si $actif n'est pas fourni, on inverse l'état actuel
        if ($actif === null) {
            $currentState = $this->createQueryBuilder('p')
                ->select('p.actif')
                ->where('p.id = :id')
                ->andWhere('p.couple = :coupleId')
                ->setParameter('id', $programmeId)
                ->setParameter('coupleId', $coupleId)
                ->getQuery()
                ->getSingleScalarResult();

            $actif = !$currentState;
        }

        $rowsAffected = $em->createQueryBuilder()
            ->update(Programme::class, 'p')
            ->set('p.actif', ':actif')
            ->set('p.dateMiseAJour', ':now')
            ->where('p.id = :id')
            ->andWhere('p.couple = :coupleId')
            ->setParameter('actif', $actif)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $programmeId)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();

        return $rowsAffected > 0;
    }

    /**
     * Duplique une activité existante
     * Crée une copie avec nouvelle heure
     */
    public function duplicateProgramme(Uuid $sourceProgrammeId, int $coupleId, ?\DateTimeInterface $nouvelleHeure = null): ?Programme
    {
        $sourceProgramme = $this->findByIdAndCouple($sourceProgrammeId, $coupleId);
        if (!$sourceProgramme) {
            return null;
        }

        $newProgramme = new Programme();
        $newProgramme->setCouple($sourceProgramme->getCouple());
        $newProgramme->setTitre($sourceProgramme->getTitre() . ' (copie)');
        $newProgramme->setDescription($sourceProgramme->getDescription());
        $newProgramme->setLieu($sourceProgramme->getLieu());
        $newProgramme->setTypeActivite($sourceProgramme->getTypeActivite());
        $newProgramme->setOrdreAffichage($this->getNextOrdreAffichage($coupleId));
        $newProgramme->setActif(false); // Nouvelle activité en brouillon par défaut

        // Nouvelle heure ou +1h par rapport à l'original
        if ($nouvelleHeure) {
            $newProgramme->setHeureDebut($nouvelleHeure);
        } else {
            $heureOriginale = clone $sourceProgramme->getHeureDebut();
            $heureOriginale->modify('+1 hour');
            $newProgramme->setHeureDebut($heureOriginale);
        }

        // Calcul automatique heure fin si elle existait
        if ($sourceProgramme->getHeureFin()) {
            $duree = $sourceProgramme->getHeureDebut()->diff($sourceProgramme->getHeureFin());
            $nouvelleFin = clone $newProgramme->getHeureDebut();
            $nouvelleFin->add($duree);
            $newProgramme->setHeureFin($nouvelleFin);
        }

        $this->save($newProgramme, true);
        return $newProgramme;
    }

    /**
     * Export du programme pour impression ou PDF
     * Retourne un tableau formaté
     */
    public function exportProgrammeForPrint(int $coupleId): array
    {
        $programmes = $this->findOrdered($coupleId);

        $exported = [];
        foreach ($programmes as $programme) {
            $exported[] = [
                'heure_debut' => $programme->getHeureDebutFormatee(),
                'heure_fin' => $programme->getHeureFinFormatee(),
                'duree' => $programme->getDuree(),
                'titre' => $programme->getTitre(),
                'description' => $programme->getDescription(),
                'lieu' => $programme->getLieu(),
                'type' => $programme->getTypeActivite()?->value,
            ];
        }

        return $exported;
    }

    /**
     * Vérifie si deux programmes ont des horaires qui se chevauchent
     * Méthode utilitaire pour détection de conflits
     */
    private function hasTimeOverlap(Programme $prog1, Programme $prog2): bool
    {
        $debut1 = $prog1->getHeureDebut();
        $fin1 = $prog1->getHeureFin() ?: $prog1->getHeureDebut();

        $debut2 = $prog2->getHeureDebut();
        $fin2 = $prog2->getHeureFin() ?: $prog2->getHeureDebut();

        return $debut1 < $fin2 && $debut2 < $fin1;
    }

    /**
     * Query Builder réutilisable pour les programmes actifs d'un couple
     * Bonne pratique : centraliser la logique commune
     */
    private function createActiveProgrammeQuery(int $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->where('p.couple = :coupleId')
            ->andWhere('p.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true);
    }

    /**
     * Sauvegarde une entité (create/update)
     * Méthode helper pour simplifier les contrôleurs
     */
    public function save(Programme $programme, bool $flush = false): void
    {
        $this->getEntityManager()->persist($programme);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     * Méthode helper pour simplifier les contrôleurs
     */
    public function remove(Programme $programme, bool $flush = false): void
    {
        $this->getEntityManager()->remove($programme);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

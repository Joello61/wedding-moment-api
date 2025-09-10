<?php

namespace App\Repository;

use App\Entity\Invite;
use App\Enumeration\StatutRsvp;
use App\Enumeration\TypeInvite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Invite>
 */
class InviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invite::class);
    }

    /**
     * Trouve tous les invités ayant confirmé leur présence
     * Statut RSVP = 'confirme'
     */
    public function findConfirmedByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.statutRsvp = :statut')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('statut', StatutRsvp::CONFIRME)
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les invités ayant un QR code généré
     * Utile pour la gestion des entrées et scans
     */
    public function findWithQrCode(int $coupleId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.qrCodeToken IS NOT NULL');

        if ($coupleId !== null) {
            $qb->andWhere('i.couple = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        return $qb->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrouve un invité via email ou téléphone
     * Recherche flexible pour l'identification
     */
    public function findByEmailOrPhone(string $contact, int $coupleId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.email = :contact OR i.telephone = :contact')
            ->setParameter('contact', $contact);

        if ($coupleId !== null) {
            $qb->andWhere('i.couple = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        return $qb->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques RSVP complètes par couple
     * Retourne : total, confirmés, déclinés, peut-être, sans réponse, accompagnants
     */
    public function countByCoupleAndStatus(int $coupleId): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select([
                'COUNT(i.id) as total',
                'COUNT(CASE WHEN i.statutRsvp = :confirme THEN i.id END) as confirmes',
                'COUNT(CASE WHEN i.statutRsvp = :decline THEN i.id END) as declines',
                'COUNT(CASE WHEN i.statutRsvp = :peut_etre THEN i.id END) as peut_etre',
                'COUNT(CASE WHEN i.statutRsvp IS NULL THEN i.id END) as sans_reponse',
                'COALESCE(SUM(CASE WHEN i.statutRsvp = :confirme THEN i.nombreAccompagnantsConfirmes END), 0) as total_accompagnants',
                'COALESCE(SUM(CASE WHEN i.statutRsvp = :confirme THEN (1 + i.nombreAccompagnantsConfirmes) END), 0) as total_personnes_confirmees'
            ])
            ->where('i.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('confirme', StatutRsvp::CONFIRME)
            ->setParameter('decline', StatutRsvp::DECLINE)
            ->setParameter('peut_etre', StatutRsvp::PEUT_ETRE);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Trouve un invité spécifique pour un couple donné
     * Sécurise l'accès en vérifiant l'appartenance au couple
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?Invite
    {
        return $this->createQueryBuilder('i')
            ->where('i.id = :id')
            ->andWhere('i.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un invité par son token QR unique
     * Méthode sécurisée pour l'authentification par QR code
     */
    public function findByQrToken(string $qrToken): ?Invite
    {
        return $this->createQueryBuilder('i')
            ->where('i.qrCodeToken = :token')
            ->setParameter('token', $qrToken)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche d'invités par nom/prénom avec recherche floue
     * Utilise ILIKE de PostgreSQL pour une recherche insensible à la casse
     */
    public function findByNameSearch(int $coupleId, string $searchTerm): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('CONCAT(i.nom, \' \', i.prenom) ILIKE :searchTerm OR CONCAT(i.prenom, \' \', i.nom) ILIKE :searchTerm')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Invités avec restrictions alimentaires
     * Utile pour la planification des repas
     */
    public function findWithDietaryRestrictions(int $coupleId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.restrictionsAlimentaires IS NOT NULL')
            ->andWhere('i.restrictionsAlimentaires != :empty')
            ->andWhere('i.statutRsvp = :confirme')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('empty', '')
            ->setParameter('confirme', StatutRsvp::CONFIRME)
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Invités par type (famille, amis, collègues, etc.)
     * Filtrage pour organisation et statistiques
     */
    public function findByType(int $coupleId, TypeInvite $type): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.typeInvite = :type')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('type', $type)
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Invités présents à la cérémonie uniquement
     */
    public function findPresentCeremonie(int $coupleId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.presentCeremonie = :present')
            ->andWhere('i.statutRsvp = :confirme')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('present', true)
            ->setParameter('confirme', StatutRsvp::CONFIRME)
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Invités présents à la réception uniquement
     */
    public function findPresentReception(int $coupleId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.presentReception = :present')
            ->andWhere('i.statutRsvp = :confirme')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('present', true)
            ->setParameter('confirme', StatutRsvp::CONFIRME)
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par type d'invité
     * Répartition famille/amis/collègues avec RSVP
     */
    public function getStatisticsByType(int $coupleId): array
    {
        $results = $this->createQueryBuilder('i')
            ->select([
                'i.typeInvite',
                'COUNT(i.id) as total',
                'COUNT(CASE WHEN i.statutRsvp = :confirme THEN i.id END) as confirmes',
                'COUNT(CASE WHEN i.statutRsvp = :decline THEN i.id END) as declines',
                'COALESCE(SUM(CASE WHEN i.statutRsvp = :confirme THEN (1 + i.nombreAccompagnantsConfirmes) END), 0) as total_personnes'
            ])
            ->where('i.couple = :coupleId')
            ->groupBy('i.typeInvite')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('confirme', StatutRsvp::CONFIRME)
            ->setParameter('decline', StatutRsvp::DECLINE)
            ->orderBy('i.typeInvite', 'ASC')
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($results as $result) {
            $type = $result['typeInvite'] ? $result['typeInvite']->value : 'non_defini';
            $formatted[$type] = [
                'total' => (int) $result['total'],
                'confirmes' => (int) $result['confirmes'],
                'declines' => (int) $result['declines'],
                'total_personnes' => (int) $result['total_personnes']
            ];
        }

        return $formatted;
    }

    /**
     * Invités sans adresse email
     * Pour relance par téléphone ou courrier
     */
    public function findWithoutEmail(int $coupleId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.email IS NULL OR i.email = :empty')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('empty', '')
            ->orderBy('i.nom', 'ASC')
            ->addOrderBy('i.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Invités ayant répondu récemment
     * Utile pour suivi des RSVP
     */
    public function findRecentRsvp(int $coupleId, int $days = 7): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify('-' . $days . ' days');

        return $this->createQueryBuilder('i')
            ->where('i.couple = :coupleId')
            ->andWhere('i.dateRsvp >= :dateLimit')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('i.dateRsvp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le statut RSVP d'un invité
     * Méthode optimisée sans chargement de l'entité complète
     */
    public function updateRsvpStatus(Uuid $inviteId, StatutRsvp $statut, int $nbAccompagnants = 0, ?string $commentaire = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->update()
            ->set('i.statutRsvp', ':statut')
            ->set('i.nombreAccompagnantsConfirmes', ':nbAccompagnants')
            ->set('i.dateRsvp', ':dateRsvp')
            ->set('i.dateMiseAJour', ':dateMiseAJour')
            ->where('i.id = :inviteId')
            ->setParameter('statut', $statut)
            ->setParameter('nbAccompagnants', $nbAccompagnants)
            ->setParameter('dateRsvp', new \DateTime())
            ->setParameter('dateMiseAJour', new \DateTime())
            ->setParameter('inviteId', $inviteId);

        if ($commentaire !== null) {
            $qb->set('i.commentaireRsvp', ':commentaire')
                ->setParameter('commentaire', $commentaire);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Génère et assigne un token QR unique
     * Évite les collisions avec vérification d'unicité
     */
    public function generateUniqueQrToken(Uuid $inviteId): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $token = bin2hex(random_bytes(16));
            $existing = $this->findOneBy(['qrCodeToken' => $token]);
            $attempt++;
        } while ($existing && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            throw new \RuntimeException('Impossible de générer un token QR unique');
        }

        $this->createQueryBuilder('i')
            ->update()
            ->set('i.qrCodeToken', ':token')
            ->set('i.dateMiseAJour', ':now')
            ->where('i.id = :inviteId')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->setParameter('inviteId', $inviteId)
            ->getQuery()
            ->execute();

        return $token;
    }

    /**
     * Import en lot d'invités
     * Optimisé pour traitement de masse
     */
    public function bulkCreate(array $invitesData, int $coupleId): array
    {
        $em = $this->getEntityManager();
        $results = ['created' => 0, 'errors' => []];

        foreach ($invitesData as $index => $data) {
            try {
                $invite = new Invite();
                $invite->setCouple($em->getReference('App\Entity\Couple', $coupleId));
                $invite->setNom($data['nom']);
                $invite->setPrenom($data['prenom']);

                if (!empty($data['email'])) {
                    $invite->setEmail($data['email']);
                }

                if (!empty($data['telephone'])) {
                    $invite->setTelephone($data['telephone']);
                }

                $em->persist($invite);
                $results['created']++;

                // Flush par batch de 50 pour optimiser
                if ($results['created'] % 50 === 0) {
                    $em->flush();
                    $em->clear();
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Ligne " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        $em->flush();
        return $results;
    }

    /**
     * Sauvegarde une entité (create/update)
     * Méthode helper pour simplifier les contrôleurs
     */
    public function save(Invite $invite, bool $flush = false): void
    {
        $this->getEntityManager()->persist($invite);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     * Méthode helper pour simplifier les contrôleurs
     */
    public function remove(Invite $invite, bool $flush = false): void
    {
        $this->getEntityManager()->remove($invite);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

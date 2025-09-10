<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Enumeration\TypeNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Trouve toutes les notifications non lues pour un invité
     * Ordonnées par date de création décroissante
     */
    public function findUnreadByInvite(Uuid $inviteId): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.invite = :inviteId')
            ->andWhere('n.lu = :lu')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('lu', false)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les notifications liées à un couple
     * Inclut les notifications globales et par invité
     */
    public function findByCouple(int $coupleId, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->where('n.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('n.dateCreation', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Notifications récentes pour le dashboard du couple
     * Avec préchargement des relations pour éviter N+1
     */
    public function findRecent(int $coupleId, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->addSelect('i')
            ->where('n.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque une notification comme lue
     * Méthode optimisée sans chargement de l'entité
     */
    public function markAsRead(Uuid $notificationId): bool
    {
        $rowsAffected = $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', ':lu')
            ->set('n.dateLecture', ':dateLecture')
            ->where('n.id = :notificationId')
            ->andWhere('n.lu = :notLu')
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTime())
            ->setParameter('notificationId', $notificationId)
            ->setParameter('notLu', false)
            ->getQuery()
            ->execute();

        return $rowsAffected > 0;
    }

    /**
     * Filtre les notifications par type pour un couple
     * Types : rsvp_confirmation, nouveau_message, etc.
     */
    public function findByType(int $coupleId, TypeNotification $type, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->addSelect('i')
            ->where('n.couple = :coupleId')
            ->andWhere('n.typeNotification = :type')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('type', $type)
            ->orderBy('n.dateCreation', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Marque toutes les notifications d'un invité comme lues
     * Utile lors de la connexion de l'invité
     */
    public function markAllAsReadByInvite(Uuid $inviteId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', ':lu')
            ->set('n.dateLecture', ':dateLecture')
            ->where('n.invite = :inviteId')
            ->andWhere('n.lu = :notLu')
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTime())
            ->setParameter('inviteId', $inviteId)
            ->setParameter('notLu', false)
            ->getQuery()
            ->execute();
    }

    /**
     * Compte les notifications non lues par invité
     * Retourne un badge count pour l'interface
     */
    public function countUnreadByInvite(Uuid $inviteId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.invite = :inviteId')
            ->andWhere('n.lu = :lu')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Notifications globales du couple (non liées à un invité spécifique)
     * Pour les annonces générales, changements de programme, etc.
     */
    public function findGlobalByCouple(int $coupleId, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.couple = :coupleId')
            ->andWhere('n.invite IS NULL')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('n.dateCreation', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques de lecture des notifications par type
     * Utile pour analyser l'engagement des invités
     */
    public function getReadingStatsByType(int $coupleId): array
    {
        $results = $this->createQueryBuilder('n')
            ->select([
                'n.typeNotification',
                'COUNT(n.id) as total',
                'COUNT(CASE WHEN n.lu = true THEN n.id END) as lues',
                'COUNT(CASE WHEN n.lu = false THEN n.id END) as non_lues'
            ])
            ->where('n.couple = :coupleId')
            ->groupBy('n.typeNotification')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($results as $result) {
            $type = $result['typeNotification']->value;
            $total = (int) $result['total'];
            $lues = (int) $result['lues'];

            $formatted[$type] = [
                'total' => $total,
                'lues' => $lues,
                'non_lues' => (int) $result['non_lues'],
                'taux_lecture' => $total > 0 ? round(($lues / $total) * 100, 2) : 0
            ];
        }

        return $formatted;
    }

    /**
     * Notifications récentes non lues pour un couple
     * Dashboard avec indicateur d'urgence
     */
    public function findRecentUnread(int $coupleId, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->addSelect('i')
            ->where('n.couple = :coupleId')
            ->andWhere('n.lu = :lu')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('lu', false)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche dans le contenu des notifications
     * Utilise ILIKE pour recherche insensible à la casse
     */
    public function findBySearchTerm(int $coupleId, string $searchTerm): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->addSelect('i')
            ->where('n.couple = :coupleId')
            ->andWhere('n.titre ILIKE :searchTerm OR n.contenu ILIKE :searchTerm')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Notifications avec lien d'action non cliqué
     * Suivi des call-to-action
     */
    public function findWithUnclickedActions(int $coupleId): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->addSelect('i')
            ->where('n.couple = :coupleId')
            ->andWhere('n.lienAction IS NOT NULL')
            ->andWhere('n.lu = :lu')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('lu', false)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Purge des anciennes notifications lues
     * Nettoyage automatique pour performance
     */
    public function purgeOldReadNotifications(int $days = 30): int
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify('-' . $days . ' days');

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.lu = :lu')
            ->andWhere('n.dateLecture < :dateLimit')
            ->setParameter('lu', true)
            ->setParameter('dateLimit', $dateLimit)
            ->getQuery()
            ->execute();
    }

    /**
     * Notifications par période pour rapports
     * Analytics des notifications envoyées
     */
    public function getNotificationsByPeriod(
        int $coupleId,
        \DateTimeInterface $dateStart,
        \DateTimeInterface $dateEnd
    ): array {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.invite', 'i')
            ->addSelect('i')
            ->where('n.couple = :coupleId')
            ->andWhere('n.dateCreation BETWEEN :dateStart AND :dateEnd')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->orderBy('n.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Top des invités les plus notifiés
     * Analyse de la communication
     */
    public function getTopNotifiedInvites(int $coupleId, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->select('i.nom, i.prenom, i.id, COUNT(n.id) as nb_notifications')
            ->innerJoin('n.invite', 'i')
            ->where('n.couple = :coupleId')
            ->groupBy('i.id, i.nom, i.prenom')
            ->orderBy('nb_notifications', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée une nouvelle notification
     * Factory method avec validation
     */
    public function createNotification(
        int $coupleId,
        TypeNotification $type,
        string $titre,
        ?string $contenu = null,
        ?Uuid $inviteId = null,
        ?string $lienAction = null
    ): Notification {
        $notification = new Notification();
        $notification->setCouple($this->getEntityManager()->getReference('App\Entity\Couple', $coupleId));
        $notification->setTypeNotification($type);
        $notification->setTitre($titre);

        if ($contenu) {
            $notification->setContenu($contenu);
        }

        if ($inviteId) {
            $notification->setInvite($this->getEntityManager()->getReference('App\Entity\Invite', $inviteId));
        }

        if ($lienAction) {
            $notification->setLienAction($lienAction);
        }

        $this->save($notification, true);
        return $notification;
    }

    /**
     * Notification en lot pour tous les invités d'un couple
     * Optimisé pour envoi de masse
     */
    public function createBulkNotification(
        int $coupleId,
        TypeNotification $type,
        string $titre,
        ?string $contenu = null,
        ?string $lienAction = null,
        array $inviteIds = []
    ): int {
        $em = $this->getEntityManager();
        $couple = $em->getReference('App\Entity\Couple', $coupleId);
        $created = 0;

        // Si aucun invité spécifié, prendre tous les invités du couple
        if (empty($inviteIds)) {
            $invites = $em->getRepository('App\Entity\Invite')->findBy(['couple' => $coupleId]);
            $inviteIds = array_map(fn($invite) => $invite->getId(), $invites);
        }

        foreach ($inviteIds as $inviteId) {
            $notification = new Notification();
            $notification->setCouple($couple);
            $notification->setInvite($em->getReference('App\Entity\Invite', $inviteId));
            $notification->setTypeNotification($type);
            $notification->setTitre($titre);

            if ($contenu) {
                $notification->setContenu($contenu);
            }

            if ($lienAction) {
                $notification->setLienAction($lienAction);
            }

            $em->persist($notification);
            $created++;

            // Flush par batch pour optimiser
            if ($created % 50 === 0) {
                $em->flush();
                $em->clear();
                $couple = $em->getReference('App\Entity\Couple', $coupleId);
            }
        }

        $em->flush();
        return $created;
    }

    /**
     * Vérifie si un invité a des notifications non lues
     * Méthode rapide pour badge indicator
     */
    public function hasUnreadNotifications(Uuid $inviteId): bool
    {
        $count = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.invite = :inviteId')
            ->andWhere('n.lu = :lu')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('lu', false)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Sauvegarde une entité (create/update)
     * Méthode helper pour simplifier les contrôleurs
     */
    public function save(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->persist($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     * Méthode helper pour simplifier les contrôleurs
     */
    public function remove(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->remove($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

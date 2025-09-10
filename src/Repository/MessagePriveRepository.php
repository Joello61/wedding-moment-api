<?php

namespace App\Repository;

use App\Entity\MessagePrive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MessagePrive>
 */
class MessagePriveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessagePrive::class);
    }

    /**
     * Récupère tous les messages liés à un couple
     *
     * @param Uuid $coupleId
     * @param array $filters Filtres optionnels (lu/non_lu, expediteur, dateFrom, dateTo)
     * @return MessagePrive[]
     */
    public function findByCouple(Uuid $coupleId, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('mp')
            ->join('mp.expediteur', 'e')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        // Application des filtres
        if (isset($filters['lu']) && $filters['lu'] !== null) {
            $qb->andWhere('mp.lu = :lu')
                ->setParameter('lu', (bool) $filters['lu']);
        }

        if (!empty($filters['expediteurId'])) {
            $qb->andWhere('mp.expediteur = :expediteurId')
                ->setParameter('expediteurId', $filters['expediteurId']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('mp.dateEnvoi >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('mp.dateEnvoi <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('(LOWER(mp.objet) LIKE :search OR LOWER(mp.contenu) LIKE :search)')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        return $qb->orderBy('mp.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les messages non lus pour un invité (notifications)
     *
     * @param Uuid $inviteId
     * @return MessagePrive[]
     */
    public function findUnreadByInvite(Uuid $inviteId): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.expediteur = :inviteId')
            ->andWhere('mp.lu = :lu')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('lu', false)
            ->orderBy('mp.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le fil de discussion complet entre un invité et un couple
     *
     * @param Uuid $inviteId
     * @param Uuid $coupleId
     * @param int|null $limit Limite le nombre de messages récupérés
     * @return MessagePrive[]
     */
    public function findConversation(Uuid $inviteId, Uuid $coupleId, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.expediteur = :inviteId')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('coupleId', $coupleId)
            ->orderBy('mp.dateEnvoi', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de messages non lus pour un invité
     *
     * @param Uuid $inviteId
     * @return int
     */
    public function countUnread(Uuid $inviteId): int
    {
        return (int) $this->createQueryBuilder('mp')
            ->select('COUNT(mp.id)')
            ->andWhere('mp.expediteur = :inviteId')
            ->andWhere('mp.lu = :lu')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère un message spécifique d'un couple (sécurité multi-couple)
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return MessagePrive|null
     */
    public function findByIdAndCouple(Uuid $id, Uuid $coupleId): ?MessagePrive
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.id = :id')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère un message par ID ou lance une exception
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return MessagePrive
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function findByIdAndCoupleOrFail(Uuid $id, Uuid $coupleId): MessagePrive
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.id = :id')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Récupère les derniers messages d'un couple pour dashboard
     *
     * @param Uuid $coupleId
     * @param int $limit
     * @return MessagePrive[]
     */
    public function findRecentByCouple(Uuid $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('mp')
            ->join('mp.expediteur', 'e')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('mp.dateEnvoi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les messages non lus pour un couple (tous invités confondus)
     *
     * @param Uuid $coupleId
     * @return int
     */
    public function countUnreadByCouple(Uuid $coupleId): int
    {
        return (int) $this->createQueryBuilder('mp')
            ->select('COUNT(mp.id)')
            ->andWhere('mp.couple = :coupleId')
            ->andWhere('mp.lu = :lu')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les conversations groupées par invité pour un couple
     *
     * @param Uuid $coupleId
     * @return array Tableau avec les derniers messages par invité
     */
    public function findConversationsByCouple(Uuid $coupleId): array
    {
        // Récupère le dernier message de chaque invité
        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(mp2.dateEnvoi)')
            ->from(MessagePrive::class, 'mp2')
            ->andWhere('mp2.couple = mp.couple')
            ->andWhere('mp2.expediteur = mp.expediteur')
            ->getDQL();

        return $this->createQueryBuilder('mp')
            ->join('mp.expediteur', 'e')
            ->andWhere('mp.couple = :coupleId')
            ->andWhere("mp.dateEnvoi = ({$subQuery})")
            ->setParameter('coupleId', $coupleId)
            ->orderBy('mp.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque un message comme lu et définit la date de lecture
     *
     * @param Uuid $id
     * @param Uuid $coupleId
     * @return bool True si le message a été trouvé et marqué comme lu
     */
    public function markAsRead(Uuid $id, Uuid $coupleId): bool
    {
        $affected = $this->getEntityManager()
            ->createQueryBuilder()
            ->update(MessagePrive::class, 'mp')
            ->set('mp.lu', ':lu')
            ->set('mp.dateLecture', ':dateLecture')
            ->andWhere('mp.id = :id')
            ->andWhere('mp.couple = :coupleId')
            ->andWhere('mp.lu = :nonLu') // Évite les mises à jour inutiles
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTime())
            ->setParameter('nonLu', false)
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    /**
     * Marque tous les messages d'un invité comme lus
     *
     * @param Uuid $inviteId
     * @param Uuid $coupleId
     * @return int Nombre de messages marqués comme lus
     */
    public function markAllAsReadByInvite(Uuid $inviteId, Uuid $coupleId): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(MessagePrive::class, 'mp')
            ->set('mp.lu', ':lu')
            ->set('mp.dateLecture', ':dateLecture')
            ->andWhere('mp.expediteur = :inviteId')
            ->andWhere('mp.couple = :coupleId')
            ->andWhere('mp.lu = :nonLu')
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTime())
            ->setParameter('nonLu', false)
            ->setParameter('inviteId', $inviteId)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();
    }

    /**
     * Marque tous les messages d'un couple comme lus
     *
     * @param Uuid $coupleId
     * @return int Nombre de messages marqués comme lus
     */
    public function markAllAsReadByCouple(Uuid $coupleId): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(MessagePrive::class, 'mp')
            ->set('mp.lu', ':lu')
            ->set('mp.dateLecture', ':dateLecture')
            ->andWhere('mp.couple = :coupleId')
            ->andWhere('mp.lu = :nonLu')
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTime())
            ->setParameter('nonLu', false)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les messages paginés avec filtres avancés
     *
     * @param Uuid $coupleId
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @return array{data: MessagePrive[], total: int, pages: int}
     */
    public function findPaginatedByCouple(Uuid $coupleId, int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('mp')
            ->join('mp.expediteur', 'e')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);

        // Application des filtres
        if (isset($filters['lu']) && $filters['lu'] !== null) {
            $qb->andWhere('mp.lu = :lu')
                ->setParameter('lu', (bool) $filters['lu']);
        }

        if (!empty($filters['expediteurId'])) {
            $qb->andWhere('mp.expediteur = :expediteurId')
                ->setParameter('expediteurId', $filters['expediteurId']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('(LOWER(mp.objet) LIKE :search OR LOWER(mp.contenu) LIKE :search OR LOWER(e.prenom) LIKE :search OR LOWER(e.nom) LIKE :search)')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('mp.dateEnvoi >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('mp.dateEnvoi <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo']);
        }

        // Compte total
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(mp.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Résultats paginés
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('mp.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
            'pages' => (int) ceil($total / $limit)
        ];
    }

    /**
     * Statistiques des messages pour un couple
     *
     * @param Uuid $coupleId
     * @return array{total: int, unread: int, conversations: int, this_week: int}
     */
    public function getStatsByCouple(Uuid $coupleId): array
    {
        $weekAgo = new \DateTime('-1 week');

        $result = $this->createQueryBuilder('mp')
            ->select([
                'COUNT(mp.id) as total',
                'SUM(CASE WHEN mp.lu = false THEN 1 ELSE 0 END) as unread',
                'COUNT(DISTINCT mp.expediteur) as conversations',
                'SUM(CASE WHEN mp.dateEnvoi >= :weekAgo THEN 1 ELSE 0 END) as this_week'
            ])
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('weekAgo', $weekAgo)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'unread' => (int) $result['unread'],
            'conversations' => (int) $result['conversations'],
            'this_week' => (int) $result['this_week']
        ];
    }

    /**
     * Récupère les invités les plus actifs en messages
     *
     * @param Uuid $coupleId
     * @param int $limit
     * @return array
     */
    public function findMostActiveInvites(Uuid $coupleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('mp')
            ->select('e.id', 'e.prenom', 'e.nom', 'COUNT(mp.id) as message_count')
            ->join('mp.expediteur', 'e')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('e.id', 'e.prenom', 'e.nom')
            ->orderBy('message_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime tous les messages d'un invité (pour nettoyage)
     *
     * @param Uuid $inviteId
     * @param Uuid $coupleId
     * @return int Nombre de messages supprimés
     */
    public function deleteByInvite(Uuid $inviteId, Uuid $coupleId): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->delete(MessagePrive::class, 'mp')
            ->andWhere('mp.expediteur = :inviteId')
            ->andWhere('mp.couple = :coupleId')
            ->setParameter('inviteId', $inviteId)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->execute();
    }

    /**
     * Sauvegarde une entité MessagePrive
     *
     * @param MessagePrive $entity
     * @param bool $flush
     */
    public function save(MessagePrive $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité MessagePrive
     *
     * @param MessagePrive $entity
     * @param bool $flush
     */
    public function remove(MessagePrive $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

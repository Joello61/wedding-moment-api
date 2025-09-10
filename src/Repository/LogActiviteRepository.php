<?php

namespace App\Repository;

use App\Entity\LogActivite;
use App\Enumeration\TypeUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<LogActivite>
 */
class LogActiviteRepository extends ServiceEntityRepository
{
    // Actions prédéfinies pour consistency
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_RSVP_UPDATE = 'rsvp_update';
    public const ACTION_PHOTO_UPLOAD = 'photo_upload';
    public const ACTION_CONTRIBUTION_CADEAU = 'contribution_cadeau';
    public const ACTION_QR_SCAN = 'qr_scan';
    public const ACTION_PROFILE_UPDATE = 'profile_update';
    public const ACTION_INVITE_CREATE = 'invite_create';
    public const ACTION_PLAYLIST_SUGGESTION = 'playlist_suggestion';
    public const ACTION_MESSAGE_PRIVE = 'message_prive';
    public const ACTION_FAQ_ACCESS = 'faq_access';
    public const ACTION_QUIZ_COMPLETE = 'quiz_complete';
    public const ACTION_SONDAGE_RESPONSE = 'sondage_response';
    public const ACTION_CAGNOTTE_VIEW = 'cagnotte_view';
    public const ACTION_MEDIA_VIEW = 'media_view';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogActivite::class);
    }

    /**
     * Trouve tous les logs d'activité d'un couple
     * Ordonnés par date décroissante (plus récent en premier)
     */
    public function findByCouple(int $coupleId, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('l.dateAction', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les logs d'un utilisateur spécifique
     * Supporte les types : couple, invite, organisateur
     */
    public function findByUser(Uuid $userId, TypeUtilisateur $typeUtilisateur, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.utilisateurId = :userId')
            ->andWhere('l.typeUtilisateur = :type')
            ->setParameter('userId', $userId)
            ->setParameter('type', $typeUtilisateur)
            ->orderBy('l.dateAction', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les logs les plus récents pour le dashboard admin
     * Inclut des informations sur l'utilisateur pour affichage
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.dateAction', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtre les logs par type d'action pour un couple
     * Actions : login, rsvp_update, photo_upload, etc.
     */
    public function findByAction(int $coupleId, string $action, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.couple = :coupleId')
            ->andWhere('l.action = :action')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('action', $action)
            ->orderBy('l.dateAction', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques d'actions pour tableau de bord
     * Compte le nombre d'occurrences par action
     */
    public function countByCoupleAndAction(int $coupleId): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('l.action, COUNT(l.id) as nb_actions')
            ->where('l.couple = :coupleId')
            ->groupBy('l.action')
            ->orderBy('nb_actions', 'DESC')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($results as $result) {
            $formatted[$result['action']] = (int) $result['nb_actions'];
        }

        return $formatted;
    }

    /**
     * Activité par jour pour graphiques
     * Retourne le nombre d'actions par jour sur une période
     */
    public function getActivityByDay(int $coupleId, int $days = 30): array
    {
        $dateStart = new \DateTime();
        $dateStart->modify('-' . $days . ' days');
        $dateStart->setTime(0, 0, 0);

        $results = $this->createQueryBuilder('l')
            ->select('DATE(l.dateAction) as jour, COUNT(l.id) as nb_actions')
            ->where('l.couple = :coupleId')
            ->andWhere('l.dateAction >= :dateStart')
            ->groupBy('jour')
            ->orderBy('jour', 'ASC')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateStart', $dateStart)
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($results as $result) {
            $formatted[$result['jour']] = (int) $result['nb_actions'];
        }

        return $formatted;
    }

    /**
     * Top utilisateurs les plus actifs
     * Classement par nombre d'actions sur une période
     */
    public function getTopActiveUsers(int $coupleId, int $days = 30, int $limit = 10): array
    {
        $dateStart = new \DateTime();
        $dateStart->modify('-' . $days . ' days');

        return $this->createQueryBuilder('l')
            ->select('l.utilisateurId, l.typeUtilisateur, COUNT(l.id) as nb_actions')
            ->where('l.couple = :coupleId')
            ->andWhere('l.dateAction >= :dateStart')
            ->andWhere('l.utilisateurId IS NOT NULL')
            ->groupBy('l.utilisateurId, l.typeUtilisateur')
            ->orderBy('nb_actions', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateStart', $dateStart)
            ->getQuery()
            ->getResult();
    }

    /**
     * Logs d'une période spécifique avec filtres avancés
     * Pour rapports d'audit détaillés
     */
    public function findByPeriodWithFilters(
        int $coupleId,
        \DateTimeInterface $dateStart,
        \DateTimeInterface $dateEnd,
        ?string $action = null,
        ?TypeUtilisateur $typeUtilisateur = null,
        ?string $adresseIp = null
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->where('l.couple = :coupleId')
            ->andWhere('l.dateAction BETWEEN :dateStart AND :dateEnd')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->orderBy('l.dateAction', 'DESC');

        if ($action !== null) {
            $qb->andWhere('l.action = :action')
                ->setParameter('action', $action);
        }

        if ($typeUtilisateur !== null) {
            $qb->andWhere('l.typeUtilisateur = :typeUtilisateur')
                ->setParameter('typeUtilisateur', $typeUtilisateur);
        }

        if ($adresseIp !== null) {
            $qb->andWhere('l.adresseIp = :adresseIp')
                ->setParameter('adresseIp', $adresseIp);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Détection d'activités suspectes
     * Recherche des patterns anormaux (trop d'actions en peu de temps)
     */
    public function findSuspiciousActivity(int $coupleId, int $minutes = 5, int $threshold = 20): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify('-' . $minutes . ' minutes');

        return $this->createQueryBuilder('l')
            ->select('l.adresseIp, l.utilisateurId, l.typeUtilisateur, COUNT(l.id) as nb_actions')
            ->where('l.couple = :coupleId')
            ->andWhere('l.dateAction >= :dateLimit')
            ->groupBy('l.adresseIp, l.utilisateurId, l.typeUtilisateur')
            ->having('COUNT(l.id) >= :threshold')
            ->orderBy('nb_actions', 'DESC')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateLimit', $dateLimit)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Logs par User-Agent (navigateurs/appareils)
     * Statistiques des plateformes utilisées
     */
    public function getUserAgentStats(int $coupleId, int $days = 30): array
    {
        $dateStart = new \DateTime();
        $dateStart->modify('-' . $days . ' days');

        $results = $this->createQueryBuilder('l')
            ->select('l.userAgent, COUNT(l.id) as nb_sessions')
            ->where('l.couple = :coupleId')
            ->andWhere('l.dateAction >= :dateStart')
            ->andWhere('l.userAgent IS NOT NULL')
            ->andWhere('l.action = :loginAction')
            ->groupBy('l.userAgent')
            ->orderBy('nb_sessions', 'DESC')
            ->setMaxResults(20)
            ->setParameter('coupleId', $coupleId)
            ->setParameter('dateStart', $dateStart)
            ->setParameter('loginAction', self::ACTION_LOGIN)
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($results as $result) {
            // Parsing simple du User-Agent pour identifier le navigateur/OS
            $userAgent = $result['userAgent'];
            $browser = $this->parseBrowserFromUserAgent($userAgent);
            $formatted[$browser] = ($formatted[$browser] ?? 0) + (int) $result['nb_sessions'];
        }

        return $formatted;
    }

    /**
     * Recherche dans les détails JSON
     * Utilise les opérateurs JSON de PostgreSQL
     */
    public function findByJsonDetails(int $coupleId, string $jsonPath, mixed $value): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.couple = :coupleId')
            ->andWhere('JSON_EXTRACT(l.detailsJson, :jsonPath) = :value')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('jsonPath', $jsonPath)
            ->setParameter('value', $value)
            ->orderBy('l.dateAction', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Purge des anciens logs
     * Suppression automatique des logs anciens pour RGPD
     */
    public function purgeOldLogs(int $months = 12): int
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify('-' . $months . ' months');

        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.dateAction < :dateLimit')
            ->setParameter('dateLimit', $dateLimit)
            ->getQuery()
            ->execute();
    }

    /**
     * Export des logs pour audit externe
     * Retourne un tableau formaté pour CSV/Excel
     */
    public function exportLogsForAudit(
        int $coupleId,
        \DateTimeInterface $dateStart,
        \DateTimeInterface $dateEnd
    ): array {
        $logs = $this->findByPeriodWithFilters($coupleId, $dateStart, $dateEnd);

        $exported = [];
        foreach ($logs as $log) {
            $exported[] = [
                'id' => $log->getId()->toString(),
                'date_action' => $log->getDateAction()->format('Y-m-d H:i:s'),
                'utilisateur_id' => $log->getUtilisateurId()?->toString(),
                'type_utilisateur' => $log->getTypeUtilisateur()?->value,
                'action' => $log->getAction(),
                'details' => json_encode($log->getDetailsJson(), JSON_UNESCAPED_UNICODE),
                'adresse_ip' => $log->getAdresseIp(),
                'user_agent' => $log->getUserAgent()
            ];
        }

        return $exported;
    }

    /**
     * Crée un nouveau log d'activité
     * Méthode utilitaire pour simplifier l'enregistrement
     */
    public function createLog(
        int $coupleId,
        string $action,
        ?Uuid $utilisateurId = null,
        ?TypeUtilisateur $typeUtilisateur = null,
        ?array $details = null,
        ?string $adresseIp = null,
        ?string $userAgent = null
    ): LogActivite {
        $log = new LogActivite();
        $log->setCouple($this->getEntityManager()->getReference('App\Entity\Couple', $coupleId));
        $log->setAction($action);

        if ($utilisateurId) {
            $log->setUtilisateurId($utilisateurId);
        }

        if ($typeUtilisateur) {
            $log->setTypeUtilisateur($typeUtilisateur);
        }

        if ($details) {
            $log->setDetailsJson($details);
        }

        if ($adresseIp) {
            $log->setAdresseIp($adresseIp);
        }

        if ($userAgent) {
            $log->setUserAgent($userAgent);
        }

        $this->save($log, true);
        return $log;
    }

    /**
     * Parse simple du User-Agent pour identifier le navigateur
     * Méthode utilitaire pour les statistiques
     */
    private function parseBrowserFromUserAgent(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'chrome')) return 'Chrome';
        if (str_contains($userAgent, 'firefox')) return 'Firefox';
        if (str_contains($userAgent, 'safari') && !str_contains($userAgent, 'chrome')) return 'Safari';
        if (str_contains($userAgent, 'edge')) return 'Edge';
        if (str_contains($userAgent, 'opera')) return 'Opera';
        if (str_contains($userAgent, 'mobile')) return 'Mobile';

        return 'Autre';
    }

    /**
     * Sauvegarde une entité (create/update)
     * Méthode helper pour simplifier les contrôleurs
     */
    public function save(LogActivite $log, bool $flush = false): void
    {
        $this->getEntityManager()->persist($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     * Méthode helper pour simplifier les contrôleurs
     */
    public function remove(LogActivite $log, bool $flush = false): void
    {
        $this->getEntityManager()->remove($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retourne la liste des actions disponibles
     * Utile pour les formulaires et validations
     */
    public function getAvailableActions(): array
    {
        return [
            self::ACTION_LOGIN => 'Connexion',
            self::ACTION_LOGOUT => 'Déconnexion',
            self::ACTION_RSVP_UPDATE => 'Mise à jour RSVP',
            self::ACTION_PHOTO_UPLOAD => 'Upload photo',
            self::ACTION_CONTRIBUTION_CADEAU => 'Contribution cadeau',
            self::ACTION_QR_SCAN => 'Scan QR code',
            self::ACTION_PROFILE_UPDATE => 'Mise à jour profil',
            self::ACTION_INVITE_CREATE => 'Création invité',
            self::ACTION_PLAYLIST_SUGGESTION => 'Suggestion playlist',
            self::ACTION_MESSAGE_PRIVE => 'Message privé',
            self::ACTION_FAQ_ACCESS => 'Consultation FAQ',
            self::ACTION_QUIZ_COMPLETE => 'Quiz complété',
            self::ACTION_SONDAGE_RESPONSE => 'Réponse sondage',
            self::ACTION_CAGNOTTE_VIEW => 'Consultation cagnotte',
            self::ACTION_MEDIA_VIEW => 'Consultation média'
        ];
    }
}

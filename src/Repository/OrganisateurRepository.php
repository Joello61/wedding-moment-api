<?php

namespace App\Repository;

use App\Entity\Organisateur;
use App\Enumeration\RoleOrganisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Repository pour la gestion des organisateurs et leurs permissions.
 *
 * @extends ServiceEntityRepository<Organisateur>
 */
class OrganisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organisateur::class);
    }

    /**
     * Trouve tous les organisateurs rattachés à un couple.
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('o.role', 'ASC')
            ->addOrderBy('o.nom', 'ASC')
            ->addOrderBy('o.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs actifs d'un couple.
     */
    public function findActiveByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('o.role', 'ASC')
            ->addOrderBy('o.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs d'un couple filtrés par rôle.
     */
    public function findByRole(int $coupleId, RoleOrganisateur $role): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.role = :role')
            ->andWhere('o.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('role', $role)
            ->setParameter('actif', true)
            ->orderBy('o.nom', 'ASC')
            ->addOrderBy('o.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un organisateur spécifique d'un couple (sécurité multi-couple).
     */
    public function findByIdAndCouple(Uuid $id, int $coupleId): ?Organisateur
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.couple = :coupleId')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si l'organisateur possède une permission spécifique.
     */
    public function hasPermission(Uuid $organisateurId, string $permission): bool
    {
        $organisateur = $this->createQueryBuilder('o')
            ->select('o.permissions')
            ->andWhere('o.id = :id')
            ->andWhere('o.actif = :actif')
            ->setParameter('id', $organisateurId, 'uuid')
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$organisateur || !$organisateur['permissions']) {
            return false;
        }

        return in_array($permission, $organisateur['permissions'], true);
    }

    /**
     * Trouve un organisateur par email (pour l'authentification).
     */
    public function findOneByEmail(string $email): ?Organisateur
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.email = :email')
            ->andWhere('o.actif = :actif')
            ->setParameter('email', $email)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les organisateurs avec une permission spécifique.
     */
    public function findByPermission(int $coupleId, string $permission): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->andWhere('JSON_CONTAINS(o.permissions, :permission) = 1')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('permission', json_encode($permission))
            ->orderBy('o.role', 'ASC')
            ->addOrderBy('o.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs avec plusieurs permissions (AND).
     */
    public function findByMultiplePermissions(int $coupleId, array $permissions): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true);

        foreach ($permissions as $index => $permission) {
            $qb->andWhere("JSON_CONTAINS(o.permissions, :permission{$index}) = 1")
                ->setParameter("permission{$index}", json_encode($permission));
        }

        return $qb->orderBy('o.role', 'ASC')
            ->addOrderBy('o.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les organisateurs par rôle pour un couple.
     */
    public function countByRole(int $coupleId): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.role', 'COUNT(o.id) as count')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->groupBy('o.role')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs avec leurs activités récentes.
     */
    public function findWithRecentActivity(int $coupleId, int $days = 30): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('o')
            ->leftJoin('o.scansQr', 's')
            ->addSelect('s')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->andWhere('s.dateCreation >= :since OR s.dateCreation IS NULL')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('since', $since)
            ->orderBy('o.role', 'ASC')
            ->addOrderBy('o.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle dans les organisateurs.
     */
    public function searchByName(int $coupleId, string $searchTerm): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->andWhere(
                'LOWER(o.nom) LIKE LOWER(:searchTerm) OR ' .
                'LOWER(o.prenom) LIKE LOWER(:searchTerm) OR ' .
                'LOWER(o.email) LIKE LOWER(:searchTerm)'
            )
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('o.nom', 'ASC')
            ->addOrderBy('o.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs récemment créés.
     */
    public function findRecentlyCreated(int $coupleId, int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.dateCreation >= :since')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('since', $since)
            ->orderBy('o.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs inactifs.
     */
    public function findInactive(int $coupleId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', false)
            ->orderBy('o.nom', 'ASC')
            ->addOrderBy('o.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ajoute une permission à un organisateur.
     */
    public function addPermission(Uuid $organisateurId, string $permission): bool
    {
        $organisateur = $this->find($organisateurId);
        if (!$organisateur) {
            return false;
        }

        $permissions = $organisateur->getPermissions() ?? [];
        if (!in_array($permission, $permissions, true)) {
            $permissions[] = $permission;

            return $this->getEntityManager()
                    ->createQueryBuilder()
                    ->update(Organisateur::class, 'o')
                    ->set('o.permissions', ':permissions')
                    ->where('o.id = :id')
                    ->setParameter('permissions', json_encode($permissions))
                    ->setParameter('id', $organisateurId, 'uuid')
                    ->getQuery()
                    ->execute() > 0;
        }

        return true;
    }

    /**
     * Retire une permission à un organisateur.
     */
    public function removePermission(Uuid $organisateurId, string $permission): bool
    {
        $organisateur = $this->find($organisateurId);
        if (!$organisateur) {
            return false;
        }

        $permissions = $organisateur->getPermissions() ?? [];
        $key = array_search($permission, $permissions, true);

        if ($key !== false) {
            unset($permissions[$key]);
            $permissions = array_values($permissions); // Réindexer le tableau

            return $this->getEntityManager()
                    ->createQueryBuilder()
                    ->update(Organisateur::class, 'o')
                    ->set('o.permissions', ':permissions')
                    ->where('o.id = :id')
                    ->setParameter('permissions', json_encode($permissions))
                    ->setParameter('id', $organisateurId, 'uuid')
                    ->getQuery()
                    ->execute() > 0;
        }

        return true;
    }

    /**
     * Met à jour les permissions d'un organisateur.
     */
    public function updatePermissions(Uuid $organisateurId, array $permissions): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(Organisateur::class, 'o')
            ->set('o.permissions', ':permissions')
            ->where('o.id = :id')
            ->setParameter('permissions', json_encode($permissions))
            ->setParameter('id', $organisateurId, 'uuid')
            ->getQuery()
            ->execute();
    }

    /**
     * Active/désactive un organisateur.
     */
    public function updateStatus(Uuid $organisateurId, bool $actif): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->update(Organisateur::class, 'o')
            ->set('o.actif', ':actif')
            ->where('o.id = :id')
            ->setParameter('actif', $actif)
            ->setParameter('id', $organisateurId, 'uuid')
            ->getQuery()
            ->execute();
    }

    /**
     * Statistiques des organisateurs pour un couple.
     */
    public function getStats(int $coupleId): array
    {
        return $this->createQueryBuilder('o')
            ->select([
                'COUNT(o.id) as totalOrganisateurs',
                'SUM(CASE WHEN o.actif = true THEN 1 ELSE 0 END) as organisateursActifs',
                'SUM(CASE WHEN o.actif = false THEN 1 ELSE 0 END) as organisateursInactifs'
            ])
            ->andWhere('o.couple = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les organisateurs ayant scanné récemment.
     */
    public function findRecentScanners(int $coupleId, int $hours = 24): array
    {
        $since = new \DateTime("-{$hours} hours");

        return $this->createQueryBuilder('o')
            ->innerJoin('o.scansQr', 's')
            ->andWhere('o.couple = :coupleId')
            ->andWhere('o.actif = :actif')
            ->andWhere('s.dateCreation >= :since')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->setParameter('since', $since)
            ->groupBy('o.id')
            ->orderBy('MAX(s.dateCreation)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les permissions disponibles basées sur les rôles.
     */
    public function getAvailablePermissionsByRole(RoleOrganisateur $role): array
    {
        return match ($role) {
            RoleOrganisateur::SCANNEUR => [
                'scan_qr',
                'voir_liste_invites'
            ],
            RoleOrganisateur::PHOTOGRAPHE => [
                'scan_qr',
                'upload_media',
                'voir_galeries',
                'voir_liste_invites'
            ],
            RoleOrganisateur::ORGANISATEUR => [
                'scan_qr',
                'upload_media',
                'voir_galeries',
                'modifier_galeries',
                'voir_liste_invites',
                'modifier_invites',
                'voir_stats',
                'export_donnees',
                'gerer_organisateurs'
            ],
        };
    }

    /**
     * Valide les permissions pour un rôle donné.
     */
    public function validatePermissionsForRole(array $permissions, RoleOrganisateur $role): array
    {
        $availablePermissions = $this->getAvailablePermissionsByRole($role);
        return array_intersect($permissions, $availablePermissions);
    }

    /**
     * Vérifie si un email existe déjà pour un couple donné.
     */
    public function emailExistsForCouple(string $email, int $coupleId, ?Uuid $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.email = :email')
            ->andWhere('o.couple = :coupleId')
            ->setParameter('email', $email)
            ->setParameter('coupleId', $coupleId);

        if ($excludeId !== null) {
            $qb->andWhere('o.id != :excludeId')
                ->setParameter('excludeId', $excludeId, 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * QueryBuilder de base pour les requêtes complexes.
     */
    public function createBaseQueryBuilder(int $coupleId): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.couple = :coupleId')
            ->setParameter('coupleId', $coupleId);
    }

    /**
     * Implémentation de PasswordUpgraderInterface pour la mise à jour automatique des mots de passe.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Organisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setMotDePasse($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Méthode pour UserProviderInterface si nécessaire.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findOneByEmail($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }

    /**
     * Sauvegarde une entité Organisateur.
     */
    public function save(Organisateur $organisateur, bool $flush = false): void
    {
        $this->getEntityManager()->persist($organisateur);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité Organisateur.
     */
    public function remove(Organisateur $organisateur, bool $flush = false): void
    {
        $this->getEntityManager()->remove($organisateur);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

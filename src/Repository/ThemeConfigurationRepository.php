<?php

namespace App\Repository;

use App\Entity\ThemeConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception as DBALException;

/**
 * @extends ServiceEntityRepository<ThemeConfiguration>
 */
class ThemeConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThemeConfiguration::class);
    }

    public function save(ThemeConfiguration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ThemeConfiguration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les configurations pour un couple.
     * Optimisé avec join pour éviter les requêtes supplémentaires.
     *
     * @return ThemeConfiguration[]
     */
    public function findByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('tc')
            ->select('tc', 'c')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->orderBy('tc.section', 'ASC')
            ->addOrderBy('tc.dateMiseAJour', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve uniquement les configurations actives pour un couple.
     * Utilise l'index composite pour optimiser la performance.
     *
     * @return ThemeConfiguration[]
     */
    public function findActiveByCouple(int $coupleId): array
    {
        return $this->createQueryBuilder('tc')
            ->select('tc', 'c')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('tc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('tc.section', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la configuration pour une section spécifique d'un couple.
     * Retourne la dernière configuration active si plusieurs existent.
     */
    public function findBySection(int $coupleId, string $section): ?ThemeConfiguration
    {
        return $this->createQueryBuilder('tc')
            ->select('tc', 'c')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('tc.section = :section')
            ->andWhere('tc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('section', $section)
            ->setParameter('actif', true)
            ->orderBy('tc.dateMiseAJour', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve une configuration spécifique par ID et couple.
     * Sécurise l'accès en vérifiant l'appartenance au couple.
     */
    public function findByIdAndCouple(int $id, int $coupleId): ?ThemeConfiguration
    {
        return $this->createQueryBuilder('tc')
            ->select('tc', 'c')
            ->innerJoin('tc.couple', 'c')
            ->where('tc.id = :id')
            ->andWhere('c.id = :coupleId')
            ->setParameter('id', $id)
            ->setParameter('coupleId', $coupleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Met à jour la configuration JSON d'une configuration spécifique.
     * Utilise une requête DQL pour optimiser la performance.
     *
     * @throws \InvalidArgumentException Si la configuration n'existe pas
     * @throws DBALException Si une erreur de base de données survient
     */
    public function updateConfigurationJson(int $id, array $configurationJson): bool
    {
        try {
            $result = $this->getEntityManager()
                ->createQuery('
                    UPDATE App\Entity\ThemeConfiguration tc
                    SET tc.configurationJson = :config, tc.dateMiseAJour = :now
                    WHERE tc.id = :id
                ')
                ->setParameter('config', $configurationJson)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $id)
                ->execute();

            return $result > 0;
        } catch (DBALException $e) {
            throw new DBALException('Erreur lors de la mise à jour de la configuration JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Met à jour la configuration JSON avec vérification de sécurité.
     * Vérifie que la configuration appartient bien au couple.
     */
    public function updateConfigurationJsonSecure(int $id, int $coupleId, array $configurationJson): bool
    {
        try {
            $result = $this->getEntityManager()
                ->createQuery('
                    UPDATE App\Entity\ThemeConfiguration tc
                    SET tc.configurationJson = :config, tc.dateMiseAJour = :now
                    WHERE tc.id = :id AND tc.couple = :coupleId
                ')
                ->setParameter('config', $configurationJson)
                ->setParameter('now', new \DateTime())
                ->setParameter('id', $id)
                ->setParameter('coupleId', $coupleId)
                ->execute();

            return $result > 0;
        } catch (DBALException $e) {
            throw new DBALException('Erreur lors de la mise à jour sécurisée de la configuration JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Trouve toutes les sections configurées pour un couple.
     * Utile pour l'interface d'administration.
     *
     * @return string[]
     */
    public function findSectionsByCouple(int $coupleId): array
    {
        $result = $this->createQueryBuilder('tc')
            ->select('DISTINCT tc.section')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('tc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('actif', true)
            ->orderBy('tc.section', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($result, 'section');
    }

    /**
     * Désactive toutes les configurations d'une section avant d'en activer une nouvelle.
     * Utile pour s'assurer qu'une seule configuration par section soit active.
     */
    public function deactivateSectionConfigurations(int $coupleId, string $section): int
    {
        return $this->getEntityManager()
            ->createQuery('
                UPDATE App\Entity\ThemeConfiguration tc
                SET tc.actif = :actif, tc.dateMiseAJour = :now
                WHERE tc.couple = :coupleId AND tc.section = :section
            ')
            ->setParameter('actif', false)
            ->setParameter('now', new \DateTime())
            ->setParameter('coupleId', $coupleId)
            ->setParameter('section', $section)
            ->execute();
    }

    /**
     * Clone une configuration existante pour un nouveau couple.
     * Utile pour les templates ou configurations par défaut.
     */
    public function cloneConfiguration(int $sourceId, int $targetCoupleId): ?ThemeConfiguration
    {
        $sourceConfig = $this->find($sourceId);
        if (!$sourceConfig) {
            return null;
        }

        $newConfig = new ThemeConfiguration();
        $newConfig->setSection($sourceConfig->getSection());
        $newConfig->setConfigurationJson($sourceConfig->getConfigurationJson());
        $newConfig->setActif($sourceConfig->isActif());

        // Assuming you have a method to get Couple entity
        $couple = $this->getEntityManager()->getReference('App\Entity\Couple', $targetCoupleId);
        $newConfig->setCouple($couple);

        $this->save($newConfig, true);

        return $newConfig;
    }

    /**
     * Recherche des configurations par contenu JSON.
     * Utilise les capacités JSON de PostgreSQL.
     */
    public function findByJsonContent(int $coupleId, string $jsonPath, mixed $value): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT tc FROM App\Entity\ThemeConfiguration tc
                INNER JOIN tc.couple c
                WHERE c.id = :coupleId
                AND JSON_EXTRACT(tc.configurationJson, :jsonPath) = :value
                AND tc.actif = true
            ')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('jsonPath', $jsonPath)
            ->setParameter('value', $value)
            ->getResult();
    }

    /**
     * Statistiques de configuration par couple.
     * Compte les configurations actives par section.
     */
    public function getConfigurationStats(int $coupleId): array
    {
        return $this->createQueryBuilder('tc')
            ->select('tc.section as section', 'COUNT(tc.id) as total', 'SUM(CASE WHEN tc.actif = true THEN 1 ELSE 0 END) as active')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->setParameter('coupleId', $coupleId)
            ->groupBy('tc.section')
            ->orderBy('tc.section', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les configurations modifiées récemment.
     * Utile pour un dashboard d'administration.
     */
    public function findRecentlyModified(int $coupleId, int $days = 7, int $limit = 10): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('tc')
            ->select('tc', 'c')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('tc.dateMiseAJour >= :since')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('since', $since)
            ->orderBy('tc.dateMiseAJour', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée avec critères multiples.
     */
    public function findByCriteria(
        ?int $coupleId = null,
        ?string $section = null,
        ?bool $actif = null,
        ?\DateTime $dateFrom = null,
        ?\DateTime $dateTo = null,
        ?string $searchInJson = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('tc')
            ->select('tc', 'c')
            ->innerJoin('tc.couple', 'c')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('tc.dateMiseAJour', 'DESC');

        if ($coupleId !== null) {
            $qb->andWhere('c.id = :coupleId')
                ->setParameter('coupleId', $coupleId);
        }

        if ($section !== null) {
            $qb->andWhere('tc.section = :section')
                ->setParameter('section', $section);
        }

        if ($actif !== null) {
            $qb->andWhere('tc.actif = :actif')
                ->setParameter('actif', $actif);
        }

        if ($dateFrom !== null) {
            $qb->andWhere('tc.dateMiseAJour >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('tc.dateMiseAJour <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        if ($searchInJson !== null) {
            // Recherche dans le contenu JSON (PostgreSQL)
            $qb->andWhere('CAST(tc.configurationJson as text) ILIKE :searchJson')
                ->setParameter('searchJson', '%' . $searchInJson . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si une section a une configuration active.
     */
    public function hasSectionActiveConfiguration(int $coupleId, string $section): bool
    {
        $count = $this->createQueryBuilder('tc')
            ->select('COUNT(tc.id)')
            ->innerJoin('tc.couple', 'c')
            ->where('c.id = :coupleId')
            ->andWhere('tc.section = :section')
            ->andWhere('tc.actif = :actif')
            ->setParameter('coupleId', $coupleId)
            ->setParameter('section', $section)
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}

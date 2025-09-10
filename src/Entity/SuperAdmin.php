<?php

namespace App\Entity;

use App\Enumeration\StatutSuperAdmin;
use App\Repository\SuperAdminRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuperAdminRepository::class)]
#[ORM\Table(name: 'super_admins')]
class SuperAdmin implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: StatutSuperAdmin::class)]
    private StatutSuperAdmin $statut = StatutSuperAdmin::ACTIF;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dernierLogin = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(?string $motDePasse): void
    {
        $this->motDePasse = $motDePasse;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): void
    {
        $this->nom = $nom;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): void
    {
        $this->prenom = $prenom;
    }

    public function getStatut(): StatutSuperAdmin
    {
        return $this->statut;
    }

    public function setStatut(StatutSuperAdmin $statut): void
    {
        $this->statut = $statut;
    }

    public function getDernierLogin(): \DateTimeInterface
    {
        return $this->dernierLogin;
    }

    public function setDernierLogin(\DateTimeInterface $dernierLogin): void
    {
        $this->dernierLogin = $dernierLogin;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): void
    {
        $this->dateCreation = $dateCreation;
    }

    public function getDateMiseAJour(): \DateTimeInterface
    {
        return $this->dateMiseAJour;
    }

    public function setDateMiseAJour(\DateTimeInterface $dateMiseAJour): void
    {
        $this->dateMiseAJour = $dateMiseAJour;
    }

    public function getRoles(): array { return ['ROLE_SUPER_ADMIN']; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->motDePasse; }
    public function eraseCredentials(): void {}

    /**
     * Active le super admin
     */
    public function activer(): static
    {
        $this->statut = StatutSuperAdmin::ACTIF;
        return $this;
    }

    /**
     * Suspend le super admin
     */
    public function suspendre(): static
    {
        $this->statut = StatutSuperAdmin::SUSPENDU;
        return $this;
    }

    /**
     * Met à jour le dernier login avec la date/heure actuelle
     */
    public function mettreAJourDernierLogin(): static
    {
        $this->dernierLogin = new \DateTime();
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }

}

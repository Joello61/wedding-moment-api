<?php

namespace App\Entity;

use App\Enumeration\RoleOrganisateur;
use App\Repository\OrganisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrganisateurRepository::class)]
#[ORM\Table(name: 'organisateurs')]
class Organisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'organisateurs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: RoleOrganisateur::class)]
    private RoleOrganisateur $role;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $permissions = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    // Relations
    #[ORM\OneToMany(targetEntity: ScanQr::class, mappedBy: 'organisateur', orphanRemoval: true)]
    private Collection $scansQr;

    public function __construct()
    {
        $this->scansQr = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    // UserInterface methods
    public function getRoles(): array
    {
        $roles = ['ROLE_ORGANISATEUR'];
        if ($this->role === RoleOrganisateur::SCANNEUR) {
            $roles[] = 'ROLE_SCANNEUR';
        } elseif ($this->role === RoleOrganisateur::PHOTOGRAPHE) {
            $roles[] = 'ROLE_PHOTOGRAPHE';
        }
        return $roles;
    }

    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->motDePasse; }
    public function eraseCredentials(): void {}

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getCouple(): ?Couple { return $this->couple; }
    public function setCouple(?Couple $couple): static { $this->couple = $couple; return $this; }
    public function getRole(): RoleOrganisateur { return $this->role; }
    public function setRole(RoleOrganisateur $role): static { $this->role = $role; return $this; }
}

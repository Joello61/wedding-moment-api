<?php

namespace App\Entity;

use App\Repository\CagnotteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CagnotteRepository::class)]
#[ORM\Table(name: 'cagnottes')]
class Cagnotte
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'cagnottes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomCagnotte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?string $objectifMontant = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $montantActuel = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $imageCagnotte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $lienPaiement = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $actif = true;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $ordreAffichage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    // Relations
    #[ORM\OneToMany(targetEntity: ContributionCadeau::class, mappedBy: 'cagnotte', orphanRemoval: true)]
    private Collection $contributions;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }

    public function getNomCagnotte(): ?string
    {
        return $this->nomCagnotte;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }
}

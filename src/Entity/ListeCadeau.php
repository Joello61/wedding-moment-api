<?php

namespace App\Entity;

use App\Enumeration\PrioriteCadeau;
use App\Repository\ListeCadeauRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ListeCadeauRepository::class)]
#[ORM\Table(name: 'liste_cadeaux')]
class ListeCadeau
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'listeCadeaux')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomCadeau = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $prixEstime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $lienAchat = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $imageCadeau = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, enumType: PrioriteCadeau::class)]
    private ?PrioriteCadeau $priorite = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    private int $quantiteSouhaitee = 1;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $quantiteRecue = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $actif = true;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $ordreAffichage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    // Relations
    #[ORM\OneToMany(targetEntity: ContributionCadeau::class, mappedBy: 'cadeau', orphanRemoval: true)]
    private Collection $contributions;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getPriorite(): ?PrioriteCadeau { return $this->priorite; }
    public function setPriorite(?PrioriteCadeau $priorite): static { $this->priorite = $priorite; return $this; }

    public function getNomCadeau(): ?string
    {
        return $this->nomCadeau;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }
}

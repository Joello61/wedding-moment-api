<?php

namespace App\Entity;

use App\Enumeration\TypeActivite;
use App\Repository\ProgrammeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProgrammeRepository::class)]
class Programme
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'programmes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre du programme est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    private ?string $description = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: 'L\'heure de début est obligatoire')]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères')]
    private ?string $lieu = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, enumType: TypeActivite::class)]
    private ?TypeActivite $typeActivite = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'L\'ordre d\'affichage doit être positif ou zéro')]
    private ?int $ordreAffichage = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCouple(): ?Couple
    {
        return $this->couple;
    }

    public function setCouple(?Couple $couple): static
    {
        $this->couple = $couple;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(\DateTimeInterface $heureDebut): static
    {
        $this->heureDebut = $heureDebut;
        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heureFin;
    }

    public function setHeureFin(?\DateTimeInterface $heureFin): static
    {
        $this->heureFin = $heureFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getTypeActivite(): ?TypeActivite
    {
        return $this->typeActivite;
    }

    public function setTypeActivite(?TypeActivite $typeActivite): static
    {
        $this->typeActivite = $typeActivite;
        return $this;
    }

    public function getOrdreAffichage(): ?int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(?int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function getDateMiseAJour(): \DateTimeInterface
    {
        return $this->dateMiseAJour;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }

    // Contrainte de validation personnalisée pour les heures
    #[Assert\Callback]
    public function validateHeures(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->heureFin && $this->heureDebut && $this->heureFin <= $this->heureDebut) {
            $context->buildViolation('L\'heure de fin doit être postérieure à l\'heure de début.')
                ->atPath('heureFin')
                ->addViolation();
        }
    }

    // Méthodes utilitaires
    public function getDuree(): ?string
    {
        if (!$this->heureDebut || !$this->heureFin) {
            return null;
        }

        $debut = $this->heureDebut;
        $fin = $this->heureFin;

        $interval = $debut->diff($fin);

        if ($interval->h > 0) {
            return sprintf('%dh%02d', $interval->h, $interval->i);
        }

        return sprintf('%d min', $interval->i);
    }

    public function getHeureDebutFormatee(): string
    {
        return $this->heureDebut ? $this->heureDebut->format('H:i') : '';
    }

    public function getHeureFinFormatee(): string
    {
        return $this->heureFin ? $this->heureFin->format('H:i') : '';
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->getHeureDebutFormatee(), $this->titre);
    }
}

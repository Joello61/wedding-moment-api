<?php

namespace App\Entity;

use App\Repository\StatistiquePresenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StatistiquePresenceRepository::class)]
#[ORM\Table(name: 'statistiques_presence')]
class StatistiquePresence
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'statistiquesPresence')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateStatistique = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $totalInvites = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $confirmesRsvp = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $presentsCeremonie = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $presentsReception = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $tauxPresence = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heurePicArrivee = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $derniereMiseAJour;

    public function __construct()
    {
        $this->derniereMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getCouple(): ?Couple { return $this->couple; }
    public function setCouple(?Couple $couple): static { $this->couple = $couple; return $this; }

    public function getDateStatistique(): ?\DateTimeInterface { return $this->dateStatistique; }
    public function setDateStatistique(\DateTimeInterface $dateStatistique): static { $this->dateStatistique = $dateStatistique; return $this; }

    public function getTotalInvites(): ?int
    {
        return $this->totalInvites;
    }

    public function setTotalInvites(?int $totalInvites): void
    {
        $this->totalInvites = $totalInvites;
    }

    public function getConfirmesRsvp(): ?int
    {
        return $this->confirmesRsvp;
    }

    public function setConfirmesRsvp(?int $confirmesRsvp): void
    {
        $this->confirmesRsvp = $confirmesRsvp;
    }

    public function getPresentsCeremonie(): ?int
    {
        return $this->presentsCeremonie;
    }

    public function setPresentsCeremonie(?int $presentsCeremonie): void
    {
        $this->presentsCeremonie = $presentsCeremonie;
    }

    public function getPresentsReception(): ?int
    {
        return $this->presentsReception;
    }

    public function setPresentsReception(?int $presentsReception): void
    {
        $this->presentsReception = $presentsReception;
    }

    public function getTauxPresence(): ?string
    {
        return $this->tauxPresence;
    }

    public function setTauxPresence(?string $tauxPresence): void
    {
        $this->tauxPresence = $tauxPresence;
    }

    public function getHeurePicArrivee(): ?\DateTimeInterface
    {
        return $this->heurePicArrivee;
    }

    public function setHeurePicArrivee(?\DateTimeInterface $heurePicArrivee): void
    {
        $this->heurePicArrivee = $heurePicArrivee;
    }

    public function getDerniereMiseAJour(): \DateTimeInterface
    {
        return $this->derniereMiseAJour;
    }

    public function setDerniereMiseAJour(\DateTimeInterface $derniereMiseAJour): void
    {
        $this->derniereMiseAJour = $derniereMiseAJour;
    }



}

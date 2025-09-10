<?php

namespace App\Entity;

use App\Repository\FaqRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FaqRepository::class)]
#[ORM\Table(name: 'faq')]
class Faq
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'faqs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $question = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $reponse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
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
    public function getId(): ?Uuid { return $this->id; }

    public function getCouple(): ?Couple { return $this->couple; }
    public function setCouple(?Couple $couple): static { $this->couple = $couple; return $this; }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): void
    {
        $this->question = $question;
    }

    public function getReponse(): ?string
    {
        return $this->reponse;
    }

    public function setReponse(?string $reponse): void
    {
        $this->reponse = $reponse;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): void
    {
        $this->categorie = $categorie;
    }

    public function getOrdreAffichage(): ?int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(?int $ordreAffichage): void
    {
        $this->ordreAffichage = $ordreAffichage;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): void
    {
        $this->actif = $actif;
    }


    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }
}

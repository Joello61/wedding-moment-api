<?php

namespace App\Entity;

use App\Repository\ThemeConfigurationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThemeConfigurationRepository::class)]
#[ORM\Table(name: 'themes_configuration')]
class ThemeConfiguration
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'themesConfiguration')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $section = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configurationJson = null;

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
    public function getSection(): ?string { return $this->section; }
    public function setSection(string $section): static { $this->section = $section; return $this; }

    public function getConfigurationJson(): ?array
    {
        return $this->configurationJson;
    }

    public function setConfigurationJson(?array $configurationJson): void
    {
        $this->configurationJson = $configurationJson;
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

<?php

namespace App\Entity;

use App\Enumeration\TypeUtilisateur;
use App\Repository\LogActiviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LogActiviteRepository::class)]
#[ORM\Table(name: 'logs_activite')]
class LogActivite
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'logsActivite')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $utilisateurId = null;

    #[ORM\Column(type: Types::STRING, length: 15, nullable: true, enumType: TypeUtilisateur::class)]
    private ?TypeUtilisateur $typeUtilisateur = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $action = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $detailsJson = null;

    #[ORM\Column(type: 'inet', nullable: true)]
    private ?string $adresseIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateAction;

    public function __construct()
    {
        $this->dateAction = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getCouple(): ?Couple { return $this->couple; }
    public function setCouple(?Couple $couple): static { $this->couple = $couple; return $this; }
    public function getUtilisateurId(): ?Uuid { return $this->utilisateurId; }
    public function setUtilisateurId(?Uuid $utilisateurId): static { $this->utilisateurId = $utilisateurId; return $this; }
    public function getTypeUtilisateur(): ?TypeUtilisateur { return $this->typeUtilisateur; }
    public function setTypeUtilisateur(?TypeUtilisateur $typeUtilisateur): static { $this->typeUtilisateur = $typeUtilisateur; return $this; }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getDetailsJson(): ?array
    {
        return $this->detailsJson;
    }

    public function setDetailsJson(?array $detailsJson): void
    {
        $this->detailsJson = $detailsJson;
    }

    public function getAdresseIp(): ?string
    {
        return $this->adresseIp;
    }

    public function setAdresseIp(?string $adresseIp): void
    {
        $this->adresseIp = $adresseIp;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

}

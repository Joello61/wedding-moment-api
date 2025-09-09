<?php

namespace App\Entity;

use App\Enumeration\TypeScan;
use App\Repository\ScanQrRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ScanQrRepository::class)]
#[ORM\Table(name: 'scans_qr')]
#[ORM\Index(name: 'idx_scans_qr_invite_id', columns: ['invite_id'])]
class ScanQr
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Invite::class, inversedBy: 'scansQr')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invite $invite = null;

    #[ORM\ManyToOne(targetEntity: Organisateur::class, inversedBy: 'scansQr')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organisateur $organisateur = null;

    #[ORM\Column(type: Types::STRING, length: 15, enumType: TypeScan::class)]
    private TypeScan $typeScan;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $heureScan;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $localisation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->heureScan = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getInvite(): ?Invite { return $this->invite; }
    public function setInvite(?Invite $invite): static { $this->invite = $invite; return $this; }
    public function getOrganisateur(): ?Organisateur { return $this->organisateur; }
    public function setOrganisateur(?Organisateur $organisateur): static { $this->organisateur = $organisateur; return $this; }
    public function getTypeScan(): TypeScan { return $this->typeScan; }
    public function setTypeScan(TypeScan $typeScan): static { $this->typeScan = $typeScan; return $this; }
    public function getHeureScan(): \DateTimeInterface { return $this->heureScan; }
    public function setHeureScan(\DateTimeInterface $heureScan): static { $this->heureScan = $heureScan; return $this; }
}

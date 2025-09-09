<?php

namespace App\Entity;

use App\Repository\ReponseSondageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReponseSondageRepository::class)]
#[ORM\Table(name: 'reponses_sondages')]
#[ORM\UniqueConstraint(name: 'uq_sondage_invite', columns: ['sondage_id', 'invite_id'])]
class ReponseSondage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Sondage::class, inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Sondage $sondage = null;

    #[ORM\ManyToOne(targetEntity: Invite::class, inversedBy: 'reponsesSondages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invite $invite = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reponse = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateReponse;

    public function __construct()
    {
        $this->dateReponse = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getSondage(): ?Sondage { return $this->sondage; }
    public function setSondage(?Sondage $sondage): static { $this->sondage = $sondage; return $this; }
    public function getInvite(): ?Invite { return $this->invite; }
    public function setInvite(?Invite $invite): static { $this->invite = $invite; return $this; }
}

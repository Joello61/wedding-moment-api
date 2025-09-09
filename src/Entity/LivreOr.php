<?php

namespace App\Entity;

use App\Repository\LivreOrRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LivreOrRepository::class)]
#[ORM\Table(name: 'livre_or')]
class LivreOr
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'messagesLivreOr')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\ManyToOne(targetEntity: Invite::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Invite $invite = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomAuteur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $emailAuteur = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $message = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $photoAssociee = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $approuve = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMessage;

    public function __construct()
    {
        $this->dateMessage = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getCouple(): ?Couple { return $this->couple; }
    public function setCouple(?Couple $couple): static { $this->couple = $couple; return $this; }
    public function getInvite(): ?Invite { return $this->invite; }
    public function setInvite(?Invite $invite): static { $this->invite = $invite; return $this; }
}

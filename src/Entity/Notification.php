<?php

namespace App\Entity;

use App\Enumeration\TypeNotification;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_couple_invite', columns: ['couple_id', 'invite_id'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\ManyToOne(targetEntity: Invite::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Invite $invite = null;

    #[ORM\Column(type: Types::STRING, length: 25, enumType: TypeNotification::class)]
    private TypeNotification $typeNotification;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenu = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $lu = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $lienAction = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLecture = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getTypeNotification(): TypeNotification { return $this->typeNotification; }
    public function setTypeNotification(TypeNotification $typeNotification): static { $this->typeNotification = $typeNotification; return $this; }
    public function isLu(): bool { return $this->lu; }
    public function setLu(bool $lu): static { $this->lu = $lu; return $this; }
}

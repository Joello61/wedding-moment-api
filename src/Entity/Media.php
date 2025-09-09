<?php

namespace App\Entity;

use App\Enumeration\TypeMedia;
use App\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'medias')]
#[ORM\Index(name: 'idx_medias_galerie_id', columns: ['galerie_id'])]
#[ORM\Index(name: 'idx_medias_tags_gin', columns: ['tags'])]
#[ORM\Index(name: 'idx_medias_galerie_type', columns: ['galerie_id', 'type_media'])]
class Media
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Galerie::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Galerie $galerie = null;

    #[ORM\ManyToOne(targetEntity: Invite::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Invite $invite = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomFichier = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Url]
    private ?string $urlFichier = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $urlMiniature = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: TypeMedia::class)]
    private TypeMedia $typeMedia;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $tailleFichier = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $format = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $largeur = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $hauteur = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $duree = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $approuve = true;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $ordreAffichage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getTypeMedia(): TypeMedia { return $this->typeMedia; }
    public function setTypeMedia(TypeMedia $typeMedia): static { $this->typeMedia = $typeMedia; return $this; }
}

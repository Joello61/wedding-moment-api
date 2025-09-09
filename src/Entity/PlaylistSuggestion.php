<?php

namespace App\Entity;

use App\Enumeration\StatutSuggestion;
use App\Repository\PlaylistSuggestionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlaylistSuggestionRepository::class)]
#[ORM\Table(name: 'playlist_suggestions')]
class PlaylistSuggestion
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'playlistSuggestions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\ManyToOne(targetEntity: Invite::class, inversedBy: 'playlistSuggestions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invite $invite = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $titreChanson = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $artiste = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $lienSpotify = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url]
    private ?string $lienYoutube = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: StatutSuggestion::class)]
    private StatutSuggestion $statut = StatutSuggestion::EN_ATTENTE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateSuggestion;

    public function __construct()
    {
        $this->dateSuggestion = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getStatut(): StatutSuggestion { return $this->statut; }
    public function setStatut(StatutSuggestion $statut): static { $this->statut = $statut; return $this; }
}

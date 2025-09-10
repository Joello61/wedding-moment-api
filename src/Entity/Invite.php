<?php

namespace App\Entity;

use App\Enumeration\StatutRsvp;
use App\Enumeration\TypeActivite;
use App\Enumeration\TypeInvite;
use App\Repository\InviteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InviteRepository::class)]
#[ORM\Table(name: 'invites')]
#[ORM\Index(name: 'idx_invites_couple_id', columns: ['couple_id'])]
#[ORM\Index(name: 'idx_invites_qr_code', columns: ['qr_code_token'])]
#[ORM\Index(name: 'idx_invites_couple_statut', columns: ['couple_id', 'statut_rsvp'])]
class Invite
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'invites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, enumType: TypeInvite::class)]
    private ?TypeInvite $typeInvite = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $accompagnantAutorise = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nombreAccompagnantsMax = 0;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $qrCodeToken = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, enumType: StatutRsvp::class)]
    private ?StatutRsvp $statutRsvp = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nombreAccompagnantsConfirmes = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $restrictionsAlimentaires = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaireRsvp = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateRsvp = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $presentCeremonie = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $presentReception = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureArrivee = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $messageCouple = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $photoVideoPartage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    // Relations
    #[ORM\OneToMany(targetEntity: ReponseSondage::class, mappedBy: 'invite', orphanRemoval: true)]
    private Collection $reponsesSondages;

    #[ORM\OneToMany(targetEntity: ResultatQuiz::class, mappedBy: 'invite', orphanRemoval: true)]
    private Collection $resultatsQuiz;

    #[ORM\OneToMany(targetEntity: PlaylistSuggestion::class, mappedBy: 'invite', orphanRemoval: true)]
    private Collection $playlistSuggestions;

    #[ORM\OneToMany(targetEntity: ContributionCadeau::class, mappedBy: 'invite', orphanRemoval: true)]
    private Collection $contributionsCadeaux;

    #[ORM\OneToMany(targetEntity: ScanQr::class, mappedBy: 'invite', orphanRemoval: true)]
    private Collection $scansQr;

    #[ORM\OneToMany(targetEntity: MessagePrive::class, mappedBy: 'expediteur', orphanRemoval: true)]
    private Collection $messagesPrivesEnvoyes;

    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'invite')]
    private Collection $medias;

    public function __construct()
    {
        $this->reponsesSondages = new ArrayCollection();
        $this->resultatsQuiz = new ArrayCollection();
        $this->playlistSuggestions = new ArrayCollection();
        $this->contributionsCadeaux = new ArrayCollection();
        $this->scansQr = new ArrayCollection();
        $this->messagesPrivesEnvoyes = new ArrayCollection();
        $this->medias = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getCouple(): ?Couple { return $this->couple; }
    public function setCouple(?Couple $couple): static { $this->couple = $couple; return $this; }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): void
    {
        $this->telephone = $telephone;
    }



    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }

    // Contrainte de validation personnalisée
    #[Assert\Callback]
    public function validateAccompagnants(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->nombreAccompagnantsConfirmes > $this->nombreAccompagnantsMax) {
            $context->buildViolation('Le nombre d\'accompagnants confirmés ne peut pas dépasser le maximum autorisé.')
                ->atPath('nombreAccompagnantsConfirmes')
                ->addViolation();
        }
    }
}

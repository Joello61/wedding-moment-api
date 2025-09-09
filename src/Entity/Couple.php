<?php

namespace App\Entity;

use App\Enumeration\StatutCouple;
use App\Repository\CoupleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoupleRepository::class)]
#[ORM\Table(name: 'couples')]
#[ORM\Index(name: 'idx_couples_modules_gin', columns: ['modules_actifs'])]
class Couple implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomMarie = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $prenomMarie = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomMarieConjoint = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $prenomMarieConjoint = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private ?string $emailAdmin = null;

    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\GreaterThanOrEqual('today')]
    private ?\DateTimeInterface $dateMariage = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureCeremonie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lieuCeremonie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adresseCeremonie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lieuReception = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adresseReception = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $domainePersonnalise = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $sousDomaine = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $themeCouleurPrimaire = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $themeCouleurSecondaire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $themePolice = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $photoCouplePrincipale = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: StatutCouple::class)]
    private StatutCouple $statut = StatutCouple::ACTIF;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modulesActifs = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    // Relations
    #[ORM\OneToMany(targetEntity: Invite::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $invites;

    #[ORM\OneToMany(targetEntity: Organisateur::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $organisateurs;

    #[ORM\OneToMany(targetEntity: HistoireCouple::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $histoiresCouple;

    #[ORM\OneToMany(targetEntity: BlogPost::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $blogPosts;

    #[ORM\OneToMany(targetEntity: Faq::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $faqs;

    #[ORM\OneToMany(targetEntity: Programme::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $programmes;

    #[ORM\OneToMany(targetEntity: Galerie::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $galeries;

    #[ORM\OneToMany(targetEntity: Sondage::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $sondages;

    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $quizzes;

    #[ORM\OneToMany(targetEntity: PlaylistSuggestion::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $playlistSuggestions;

    #[ORM\OneToMany(targetEntity: ListeCadeau::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $listeCadeaux;

    #[ORM\OneToMany(targetEntity: Cagnotte::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $cagnottes;

    #[ORM\OneToMany(targetEntity: StatistiquePresence::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $statistiquesPresence;

    #[ORM\OneToMany(targetEntity: LivreOr::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $messagesLivreOr;

    #[ORM\OneToMany(targetEntity: MessagePrive::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $messagesPrives;

    #[ORM\OneToMany(targetEntity: ThemeConfiguration::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $themesConfiguration;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $notifications;

    #[ORM\OneToMany(targetEntity: LogActivite::class, mappedBy: 'couple', orphanRemoval: true)]
    private Collection $logsActivite;

    public function __construct()
    {
        $this->invites = new ArrayCollection();
        $this->organisateurs = new ArrayCollection();
        $this->histoiresCouple = new ArrayCollection();
        $this->blogPosts = new ArrayCollection();
        $this->faqs = new ArrayCollection();
        $this->programmes = new ArrayCollection();
        $this->galeries = new ArrayCollection();
        $this->sondages = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
        $this->playlistSuggestions = new ArrayCollection();
        $this->listeCadeaux = new ArrayCollection();
        $this->cagnottes = new ArrayCollection();
        $this->statistiquesPresence = new ArrayCollection();
        $this->messagesLivreOr = new ArrayCollection();
        $this->messagesPrives = new ArrayCollection();
        $this->themesConfiguration = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->logsActivite = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
    public function getNomMarie(): ?string { return $this->nomMarie; }
    public function setNomMarie(string $nomMarie): static { $this->nomMarie = $nomMarie; return $this; }
    public function getPrenomMarie(): ?string { return $this->prenomMarie; }
    public function setPrenomMarie(string $prenomMarie): static { $this->prenomMarie = $prenomMarie; return $this; }
    public function getNomMarieConjoint(): ?string { return $this->nomMarieConjoint; }
    public function setNomMarieConjoint(string $nomMarieConjoint): static { $this->nomMarieConjoint = $nomMarieConjoint; return $this; }
    public function getPrenomMarieConjoint(): ?string { return $this->prenomMarieConjoint; }
    public function setPrenomMarieConjoint(string $prenomMarieConjoint): static { $this->prenomMarieConjoint = $prenomMarieConjoint; return $this; }
    public function getEmailAdmin(): ?string { return $this->emailAdmin; }
    public function setEmailAdmin(string $emailAdmin): static { $this->emailAdmin = $emailAdmin; return $this; }
    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): static { $this->motDePasse = $motDePasse; return $this; }
    public function getDateMariage(): ?\DateTimeInterface { return $this->dateMariage; }
    public function setDateMariage(\DateTimeInterface $dateMariage): static { $this->dateMariage = $dateMariage; return $this; }

    // UserInterface methods
    public function getRoles(): array { return ['ROLE_COUPLE']; }
    public function getUserIdentifier(): string { return $this->emailAdmin; }
    public function getPassword(): string { return $this->motDePasse; }
    public function eraseCredentials(): void {}

    public function getStatut(): StatutCouple { return $this->statut; }
    public function setStatut(StatutCouple $statut): static { $this->statut = $statut; return $this; }

    // Autres getters/setters...
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }
}

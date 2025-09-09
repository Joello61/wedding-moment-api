<?php

namespace App\Entity;

use App\Enumeration\StatutContribution;
use App\Repository\ContributionCadeauRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContributionCadeauRepository::class)]
#[ORM\Table(name: 'contributions_cadeaux')]
class ContributionCadeau
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Invite::class, inversedBy: 'contributionsCadeaux')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invite $invite = null;

    #[ORM\ManyToOne(targetEntity: ListeCadeau::class, inversedBy: 'contributions')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ListeCadeau $cadeau = null;

    #[ORM\ManyToOne(targetEntity: Cagnotte::class, inversedBy: 'contributions')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Cagnotte $cagnotte = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Assert\LessThan(value: 10000, message: 'Le montant ne peut pas dépasser {{ compared_value }}€')]
    private ?string $montant = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères')]
    private ?string $message = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: StatutContribution::class)]
    private StatutContribution $statut = StatutContribution::EN_ATTENTE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateContribution;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateConfirmation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLivraison = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères')]
    private ?string $notesAdmin = null;

    public function __construct()
    {
        $this->dateContribution = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInvite(): ?Invite
    {
        return $this->invite;
    }

    public function setInvite(?Invite $invite): static
    {
        $this->invite = $invite;
        return $this;
    }

    public function getCadeau(): ?ListeCadeau
    {
        return $this->cadeau;
    }

    public function setCadeau(?ListeCadeau $cadeau): static
    {
        $this->cadeau = $cadeau;
        return $this;
    }

    public function getCagnotte(): ?Cagnotte
    {
        return $this->cagnotte;
    }

    public function setCagnotte(?Cagnotte $cagnotte): static
    {
        $this->cagnotte = $cagnotte;
        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(?string $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getMontantFloat(): ?float
    {
        return $this->montant ? (float) $this->montant : null;
    }

    public function setMontantFloat(?float $montant): static
    {
        $this->montant = $montant ? number_format($montant, 2, '.', '') : null;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatut(): StatutContribution
    {
        return $this->statut;
    }

    public function setStatut(StatutContribution $statut): static
    {
        $this->statut = $statut;

        // Met à jour automatiquement les dates selon le statut
        if ($statut === StatutContribution::CONFIRME && !$this->dateConfirmation) {
            $this->dateConfirmation = new \DateTime();
        } elseif ($statut === StatutContribution::LIVRE && !$this->dateLivraison) {
            $this->dateLivraison = new \DateTime();
        }

        return $this;
    }

    public function getDateContribution(): \DateTimeInterface
    {
        return $this->dateContribution;
    }

    public function setDateContribution(\DateTimeInterface $dateContribution): static
    {
        $this->dateContribution = $dateContribution;
        return $this;
    }

    public function getDateConfirmation(): ?\DateTimeInterface
    {
        return $this->dateConfirmation;
    }

    public function setDateConfirmation(?\DateTimeInterface $dateConfirmation): static
    {
        $this->dateConfirmation = $dateConfirmation;
        return $this;
    }

    public function getDateLivraison(): ?\DateTimeInterface
    {
        return $this->dateLivraison;
    }

    public function setDateLivraison(?\DateTimeInterface $dateLivraison): static
    {
        $this->dateLivraison = $dateLivraison;
        return $this;
    }

    public function getNotesAdmin(): ?string
    {
        return $this->notesAdmin;
    }

    public function setNotesAdmin(?string $notesAdmin): static
    {
        $this->notesAdmin = $notesAdmin;
        return $this;
    }

    // Contrainte de validation personnalisée
    #[Assert\Callback]
    public function validateCadeauOuCagnotte(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (!$this->cadeau && !$this->cagnotte) {
            $context->buildViolation('Une contribution doit être liée soit à un cadeau soit à une cagnotte.')
                ->atPath('cadeau')
                ->addViolation();
        }

        if ($this->cadeau && $this->cagnotte) {
            $context->buildViolation('Une contribution ne peut pas être liée à la fois à un cadeau et à une cagnotte.')
                ->atPath('cagnotte')
                ->addViolation();
        }

        // Validation du montant pour les cagnottes
        if ($this->cagnotte && !$this->montant) {
            $context->buildViolation('Le montant est obligatoire pour une contribution à une cagnotte.')
                ->atPath('montant')
                ->addViolation();
        }
    }

    // Méthodes utilitaires
    public function isCadeauContribution(): bool
    {
        return $this->cadeau !== null;
    }

    public function isCagnotteContribution(): bool
    {
        return $this->cagnotte !== null;
    }

    public function getTypeContribution(): string
    {
        if ($this->isCadeauContribution()) {
            return 'cadeau';
        }

        if ($this->isCagnotteContribution()) {
            return 'cagnotte';
        }

        return 'inconnue';
    }

    public function getNomContribution(): string
    {
        if ($this->cadeau) {
            return $this->cadeau->getNomCadeau();
        }

        if ($this->cagnotte) {
            return $this->cagnotte->getNomCagnotte();
        }

        return 'Contribution inconnue';
    }

    public function getNomContributeur(): string
    {
        if (!$this->invite) {
            return 'Invité inconnu';
        }

        return $this->invite->getPrenom() . ' ' . $this->invite->getNom();
    }

    public function getMontantFormatte(): string
    {
        if (!$this->montant) {
            return 'N/A';
        }

        return number_format((float) $this->montant, 2, ',', ' ') . ' €';
    }

    public function canBeConfirmed(): bool
    {
        return $this->statut === StatutContribution::EN_ATTENTE;
    }

    public function canBeDelivered(): bool
    {
        return $this->statut === StatutContribution::CONFIRME && $this->isCadeauContribution();
    }

    public function __toString(): string
    {
        $type = $this->getTypeContribution();
        $nom = $this->getNomContribution();
        $montant = $this->montant ? ' - ' . $this->getMontantFormatte() : '';

        return sprintf('%s: %s%s', ucfirst($type), $nom, $montant);
    }
}

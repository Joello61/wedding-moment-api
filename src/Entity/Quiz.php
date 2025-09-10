<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quiz')]
#[ORM\Index(name: 'idx_quiz_questions_gin', columns: ['questions_json'])]
class Quiz
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Couple::class, inversedBy: 'quizzes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Couple $couple = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, columnDefinition: "jsonb")]
    #[Assert\NotNull]
    private ?array $questionsJson = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateMiseAJour;

    // Relations
    #[ORM\OneToMany(targetEntity: ResultatQuiz::class, mappedBy: 'quiz', orphanRemoval: true)]
    private Collection $resultats;

    public function __construct()
    {
        $this->resultats = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMiseAJour = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->dateMiseAJour = new \DateTime();
    }

    public function getCouple(): ?Couple
    {
        return $this->couple;
    }

    public function setCouple(?Couple $couple): void
    {
        $this->couple = $couple;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): void
    {
        $this->titre = $titre;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getQuestionsJson(): ?array
    {
        return $this->questionsJson;
    }

    public function setQuestionsJson(?array $questionsJson): void
    {
        $this->questionsJson = $questionsJson;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): void
    {
        $this->actif = $actif;
    }

    public function getResultats(): Collection
    {
        return $this->resultats;
    }

    public function setResultats(Collection $resultats): void
    {
        $this->resultats = $resultats;
    }


}

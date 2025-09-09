<?php

namespace App\Entity;

use App\Repository\ResultatQuizRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ResultatQuizRepository::class)]
#[ORM\Table(name: 'resultats_quiz')]
#[ORM\UniqueConstraint(name: 'uq_quiz_invite', columns: ['quiz_id', 'invite_id'])]
class ResultatQuiz
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    #[ORM\ManyToOne(targetEntity: Invite::class, inversedBy: 'resultatsQuiz')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invite $invite = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $score = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $reponsesJson = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $tempsCompletion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCompletion;

    public function __construct()
    {
        $this->dateCompletion = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?Uuid { return $this->id; }
}

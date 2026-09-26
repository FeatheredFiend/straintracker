<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A rating level (Fantastic, Nice, Mids, Terrible). Score gives the levels a
 * sort order and lets the UI average/compare them; higher is better.
 */
#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[UniqueEntity(fields: ['label'], message: 'A rating with this label already exists.')]
#[UniqueEntity(fields: ['score'], message: 'Another rating already uses this score.')]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    private string $label = '';

    #[ORM\Column(type: 'smallint', unique: true)]
    #[Assert\Range(min: 0, max: 100)]
    private int $score = 0;

    #[ORM\Column(length: 7, options: ['default' => '#10b981'])]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Colour must be a hex value like #10b981.')]
    private string $colour = '#10b981';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getColour(): string
    {
        return $this->colour;
    }

    public function setColour(string $colour): static
    {
        $this->colour = strtolower(trim($colour));

        return $this;
    }
}

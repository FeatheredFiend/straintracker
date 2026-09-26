<?php

namespace App\Entity;

use App\Repository\StrainTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Indica / Sativa / Hybrid spectrum. Position orders the types along that
 * spectrum in dropdowns and filters rather than alphabetically.
 */
#[ORM\Entity(repositoryClass: StrainTypeRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'A type with this name already exists.')]
class StrainType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    private string $name = '';

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 999)]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}

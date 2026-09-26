<?php

namespace App\Entity;

use App\Repository\TerpeneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TerpeneRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'A terpene with this name already exists.')]
class Terpene
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $name = '';

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $aroma = null;

    #[ORM\Column(length: 7, options: ['default' => '#10b981'])]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Colour must be a hex value like #10b981.')]
    private string $colour = '#10b981';

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

    public function getAroma(): ?string
    {
        return $this->aroma;
    }

    public function setAroma(?string $aroma): static
    {
        $aroma = null === $aroma ? null : trim($aroma);
        $this->aroma = '' === $aroma ? null : $aroma;

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

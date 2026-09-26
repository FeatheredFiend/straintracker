<?php

namespace App\Entity;

use App\Repository\StrainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A strain that has been tried. Ratings are recorded per person: aRating is
 * Anarlia's, mRating is Martyn's (labelled that way in the UI only).
 */
#[ORM\Entity(repositoryClass: StrainRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_strain_brand_name', columns: ['brand_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['brand', 'name'], message: 'This brand already has a strain with that name.', errorPath: 'name')]
class Strain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choose a brand.')]
    private ?Brand $brand = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?StrainType $type = null;

    /** Stored as a decimal string by Doctrine to avoid float rounding. */
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $thcPercent = null;

    /** Parent strains / lineage, free text as it appears on the packaging. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $genetics = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 99999)]
    private ?string $price = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'a_rating_id', nullable: true)]
    private ?Rating $aRating = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'm_rating_id', nullable: true)]
    private ?Rating $mRating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, Terpene> */
    #[ORM\ManyToMany(targetEntity: Terpene::class)]
    #[ORM\JoinTable(name: 'strain_terpene')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $terpenes;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->terpenes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getType(): ?StrainType
    {
        return $this->type;
    }

    public function setType(?StrainType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getThcPercent(): ?string
    {
        return $this->thcPercent;
    }

    public function setThcPercent(?string $thcPercent): static
    {
        $this->thcPercent = $thcPercent;

        return $this;
    }

    public function getGenetics(): ?string
    {
        return $this->genetics;
    }

    public function setGenetics(?string $genetics): static
    {
        $genetics = null === $genetics ? null : trim($genetics);
        $this->genetics = '' === $genetics ? null : $genetics;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getARating(): ?Rating
    {
        return $this->aRating;
    }

    public function setARating(?Rating $aRating): static
    {
        $this->aRating = $aRating;

        return $this;
    }

    public function getMRating(): ?Rating
    {
        return $this->mRating;
    }

    public function setMRating(?Rating $mRating): static
    {
        $this->mRating = $mRating;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $notes = null === $notes ? null : trim($notes);
        $this->notes = '' === $notes ? null : $notes;

        return $this;
    }

    /** @return Collection<int, Terpene> */
    public function getTerpenes(): Collection
    {
        return $this->terpenes;
    }

    public function addTerpene(Terpene $terpene): static
    {
        if (!$this->terpenes->contains($terpene)) {
            $this->terpenes->add($terpene);
        }

        return $this;
    }

    public function removeTerpene(Terpene $terpene): static
    {
        $this->terpenes->removeElement($terpene);

        return $this;
    }

    /** @param iterable<Terpene> $terpenes */
    public function replaceTerpenes(iterable $terpenes): static
    {
        $this->terpenes->clear();
        foreach ($terpenes as $terpene) {
            $this->addTerpene($terpene);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

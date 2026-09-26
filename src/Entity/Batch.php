<?php

namespace App\Entity;

use App\Repository\BatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One batch of a strain (a specific packaging run), identified by the
 * batch number printed on the tub. A strain gathers batches over time as
 * it's bought again.
 */
#[ORM\Entity(repositoryClass: BatchRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_batch_strain_number', columns: ['strain_id', 'batch_number'])]
class Batch
{
    public const MAX_NUMBER_LENGTH = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'batches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Strain $strain = null;

    #[ORM\Column(length: self::MAX_NUMBER_LENGTH)]
    private string $batchNumber = '';

    /** Null only while an invalid submission is being rejected. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStrain(): ?Strain
    {
        return $this->strain;
    }

    public function setStrain(?Strain $strain): static
    {
        $this->strain = $strain;

        return $this;
    }

    public function getBatchNumber(): string
    {
        return $this->batchNumber;
    }

    public function setBatchNumber(string $batchNumber): static
    {
        $this->batchNumber = trim($batchNumber);

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }
}

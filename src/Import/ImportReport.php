<?php

namespace App\Import;

final class ImportReport implements \JsonSerializable
{
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;
    /** @var list<string> */
    private array $newBrands = [];
    /** @var list<array{row: int, message: string}> */
    private array $warnings = [];

    public function __construct(private readonly bool $dryRun)
    {
    }

    public function created(): void
    {
        ++$this->created;
    }

    public function updated(): void
    {
        ++$this->updated;
    }

    public function skip(int $row, string $message): void
    {
        ++$this->skipped;
        $this->warn($row, $message);
    }

    public function newBrand(string $name): void
    {
        $this->newBrands[] = $name;
    }

    public function warn(int $row, string $message): void
    {
        $this->warnings[] = ['row' => $row, 'message' => $message];
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    /** @return list<array{row: int, message: string}> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function jsonSerialize(): array
    {
        return [
            'dryRun' => $this->dryRun,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'newBrands' => $this->newBrands,
            'warnings' => $this->warnings,
        ];
    }
}

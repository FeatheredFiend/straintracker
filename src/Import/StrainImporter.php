<?php

namespace App\Import;

use App\Entity\Brand;
use App\Entity\Rating;
use App\Entity\Strain;
use App\Entity\StrainType;
use App\Entity\Terpene;
use App\Repository\BrandRepository;
use App\Repository\RatingRepository;
use App\Repository\StrainRepository;
use App\Repository\StrainTypeRepository;
use App\Repository\TerpeneRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports a strain spreadsheet laid out like the original WEED.xlsx
 * (Brand, Strain, Type, THC %, Terpenes, Genetics, Rating, Price).
 *
 * Rows are matched to existing strains on brand + strain name, so running
 * the same file twice updates rather than duplicates. Missing brands and
 * types are created; ratings must already exist. A dry run does all the
 * work inside a transaction and rolls it back, giving a preview report.
 */
class StrainImporter
{
    /** Normalised header text => field it feeds. */
    private const HEADERS = [
        'brand' => 'brand',
        'strain' => 'name',
        'strain name' => 'name',
        'name' => 'name',
        'type' => 'type',
        'thc %' => 'thc',
        'thc%' => 'thc',
        'thc' => 'thc',
        'terpenes' => 'terpenes',
        'terps' => 'terpenes',
        'genetics' => 'genetics',
        'lineage' => 'genetics',
        'rating' => 'rating',
        'anarlia rating' => 'aRating',
        'a rating' => 'aRating',
        'arating' => 'aRating',
        'martyn rating' => 'mRating',
        'm rating' => 'mRating',
        'mrating' => 'mRating',
        'price' => 'price',
        'notes' => 'notes',
    ];

    /** @var array<string, Brand> */
    private array $brands = [];
    /** @var array<string, StrainType> */
    private array $types = [];
    /** @var array<string, Terpene> */
    private array $terpenes = [];
    /** @var array<string, Rating> */
    private array $ratings = [];
    /** @var array<string, Strain> */
    private array $strains = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SpreadsheetReader $reader,
        private readonly BrandRepository $brandRepository,
        private readonly StrainTypeRepository $typeRepository,
        private readonly TerpeneRepository $terpeneRepository,
        private readonly RatingRepository $ratingRepository,
        private readonly StrainRepository $strainRepository,
    ) {
    }

    public function import(string $path, string $originalName, bool $dryRun): ImportReport
    {
        $rows = $this->reader->read($path, $originalName);
        if (\count($rows) < 2) {
            throw new ImportException('The sheet needs a header row and at least one strain.');
        }

        $columns = $this->mapHeaders(array_shift($rows));
        $report = new ImportReport($dryRun);

        $this->entityManager->beginTransaction();
        try {
            $this->loadLookups();
            /** @var array<string, int> $seenAt strain key => sheet row it first appeared on */
            $seenAt = [];

            foreach ($rows as $i => $row) {
                $this->importRow($i + 2, $this->pick($row, $columns), $report, $seenAt);
            }

            $this->entityManager->flush();
            if ($dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
            } else {
                $this->entityManager->commit();
            }
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->entityManager->clear();
            throw $e;
        }

        return $report;
    }

    /**
     * @param array<string, ?string> $cells
     * @param array<string, int>     $seenAt
     */
    private function importRow(int $line, array $cells, ImportReport $report, array &$seenAt): void
    {
        $brandName = CellParser::clean($cells['brand']);
        $strainName = CellParser::clean($cells['name']);
        if (null === $brandName || null === $strainName) {
            $report->skip($line, 'No brand or strain name - row skipped.');

            return;
        }

        $terpeneCell = $cells['terpenes'];
        $geneticsCell = $cells['genetics'];
        $terpenes = CellParser::terpenes($terpeneCell);

        if ([] === $terpenes['names'] && [] !== $terpenes['leftover'] && CellParser::isTerpeneList($geneticsCell)) {
            [$terpeneCell, $geneticsCell] = [$geneticsCell, $terpeneCell];
            $terpenes = CellParser::terpenes($terpeneCell);
            $report->warn($line, "{$strainName}: terpenes and genetics were in each other's columns - swapped them back.");
        } elseif ([] !== $terpenes['names'] && CellParser::isTerpeneList($geneticsCell)) {
            $geneticsCell = null;
            $report->warn($line, "{$strainName}: genetics column just repeated the terpenes - left genetics blank.");
        }
        if ([] !== $terpenes['leftover']) {
            $report->warn($line, sprintf('%s: ignored unrecognised terpene text "%s".', $strainName, implode(' ', $terpenes['leftover'])));
        }

        $ratings = null !== $cells['aRating'] || null !== $cells['mRating']
            ? ['a' => CellParser::ratingLabel($cells['aRating'] ?? ''), 'm' => CellParser::ratingLabel($cells['mRating'] ?? '')]
            : CellParser::ratings($cells['rating']);
        $rawRating = CellParser::clean(implode(' ', array_filter([$cells['rating'], $cells['aRating'], $cells['mRating']])));
        if (null !== $rawRating && null === $ratings['a'] && null === $ratings['m']) {
            $report->warn($line, "{$strainName}: rating \"{$rawRating}\" isn't a known rating - left blank.");
        }

        $brand = $this->brand($brandName, $report);
        $key = $this->strainKey($brand, $strainName);

        $strain = $this->strains[$key] ?? null;
        $duplicate = isset($seenAt[$key]);
        if ($duplicate) {
            $report->warn($line, "{$strainName} ({$brand->getName()}) repeats row {$seenAt[$key]} - merged into it.");
        } elseif (null === $strain) {
            $strain = (new Strain())->setBrand($brand)->setName($strainName);
            $this->entityManager->persist($strain);
            $this->strains[$key] = $strain;
            $report->created();
        } else {
            $report->updated();
        }
        $seenAt[$key] ??= $line;

        $strain
            ->setType($this->type(CellParser::type($cells['type'])))
            ->setThcPercent(CellParser::thc($cells['thc']) ?? $strain->getThcPercent())
            ->setGenetics(CellParser::genetics($geneticsCell))
            ->setPrice(CellParser::price($cells['price']) ?? $strain->getPrice())
            ->setARating($this->rating($ratings['a']))
            ->setMRating($this->rating($ratings['m']));

        if (null !== $cells['notes']) {
            $strain->setNotes($cells['notes']);
        }

        $terpeneEntities = array_map($this->terpene(...), $terpenes['names']);
        if ($duplicate) {
            foreach ($terpeneEntities as $terpene) {
                $strain->addTerpene($terpene);
            }
        } else {
            $strain->replaceTerpenes($terpeneEntities);
        }
    }

    /**
     * @param list<?string> $header
     *
     * @return array<string, int> field => column index
     */
    private function mapHeaders(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $text) {
            $normalised = mb_strtolower(CellParser::clean($text) ?? '');
            if (isset(self::HEADERS[$normalised])) {
                $columns[self::HEADERS[$normalised]] ??= $index;
            }
        }

        if (!isset($columns['brand'], $columns['name'])) {
            throw new ImportException('Could not find "Brand" and "Strain" columns in the header row.');
        }

        return $columns;
    }

    /**
     * @param list<?string>      $row
     * @param array<string, int> $columns
     *
     * @return array<string, ?string>
     */
    private function pick(array $row, array $columns): array
    {
        $cells = [];
        foreach (array_unique(self::HEADERS) as $field) {
            $cells[$field] = isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;
        }

        return $cells;
    }

    private function loadLookups(): void
    {
        $this->brands = $this->types = $this->terpenes = $this->ratings = $this->strains = [];
        foreach ($this->brandRepository->findAll() as $brand) {
            $this->brands[mb_strtolower($brand->getName())] = $brand;
        }
        foreach ($this->typeRepository->findAll() as $type) {
            $this->types[mb_strtolower($type->getName())] = $type;
        }
        foreach ($this->terpeneRepository->findAll() as $terpene) {
            $this->terpenes[mb_strtolower($terpene->getName())] = $terpene;
        }
        foreach ($this->ratingRepository->findAll() as $rating) {
            $this->ratings[mb_strtolower($rating->getLabel())] = $rating;
        }
        foreach ($this->strainRepository->findAllWithRelations() as $strain) {
            $this->strains[$this->strainKey($strain->getBrand(), $strain->getName())] = $strain;
        }
    }

    private function strainKey(Brand $brand, string $name): string
    {
        return mb_strtolower($brand->getName()."\0".$name);
    }

    private function brand(string $name, ImportReport $report): Brand
    {
        $key = mb_strtolower($name);
        if (!isset($this->brands[$key])) {
            $this->brands[$key] = (new Brand())->setName($name);
            $this->entityManager->persist($this->brands[$key]);
            $report->newBrand($name);
        }

        return $this->brands[$key];
    }

    private function type(?string $name): ?StrainType
    {
        if (null === $name) {
            return null;
        }
        $key = mb_strtolower($name);
        if (!isset($this->types[$key])) {
            $position = 10 * (\count($this->types) + 1);
            $this->types[$key] = (new StrainType())->setName($name)->setPosition($position);
            $this->entityManager->persist($this->types[$key]);
        }

        return $this->types[$key];
    }

    private function terpene(string $name): Terpene
    {
        $key = mb_strtolower($name);
        if (!isset($this->terpenes[$key])) {
            $this->terpenes[$key] = (new Terpene())->setName($name);
            $this->entityManager->persist($this->terpenes[$key]);
        }

        return $this->terpenes[$key];
    }

    private function rating(?string $label): ?Rating
    {
        return null === $label ? null : ($this->ratings[mb_strtolower($label)] ?? null);
    }
}

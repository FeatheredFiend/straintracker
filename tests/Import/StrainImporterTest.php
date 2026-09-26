<?php

namespace App\Tests\Import;

use App\Entity\Strain;
use App\Import\ImportException;
use App\Import\SpreadsheetReader;
use App\Import\StrainImporter;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StrainImporterTest extends KernelTestCase
{
    use DatabaseTestTrait;

    /** Rows with the same quirks as the original sheet (names made up). */
    private const SHEET = [
        ['Brand', 'Strain ', 'Type', 'THC %', 'Terpenes', 'Genetics', 'Rating', 'Price'],
        ['Northfield', 'Citrus Haze', 'Indica Hybrid', '28', 'Limonene & Myrcene & Carryophyllene & Terpinolene', 'Skunk #1 & Lemon Tree', 'Terrible for A Nice for M', '£80'],
        ['Harbour ', 'Orange Peel', 'indica', '25', 'Orange Bud x White Widow', 'Limonene & Myrcene & Caryophyllene & Pinene', 'Fantastic', '£55'],
        ['Oakridge', 'Mango Sherbet', 'Hybrid', '28', 'Limonene Myrcene & Caryophyllene', 'Mango & Sherbet', 'Fantastic', '£59'],
        ['Oakridge', 'Mango Sherbet', 'Hybrid', '28', 'Myrcene, Limonene &Caryophyllene', 'Mango & Sherbet', 'Fantastic', '£59'],
        ['Hillside', 'Sticky Pine ', 'Hybrid', '29', 'Caryophyllene, Limonene & Myrcene', 'Caryophyllene, Limonene & Myrcene', 'Fantastic', '£75'],
        ['Seaview ', 'Morning Haze', 'Sativa', '28', 'Unknown Terps ', 'Unknown Lineage', 'Fantastic', '£49:50'],
        ['', '', '', '', '', '', '', ''],
    ];

    private string $csv;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();

        $this->csv = tempnam(sys_get_temp_dir(), 'sheet');
        $handle = fopen($this->csv, 'w');
        foreach (self::SHEET as $row) {
            fputcsv($handle, $row, escape: '');
        }
        fclose($handle);
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    private function importer(): StrainImporter
    {
        return self::getContainer()->get(StrainImporter::class);
    }

    private function strain(string $name): Strain
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Strain::class)->findOneBy(['name' => $name]);
    }

    private function terpeneNames(Strain $strain): array
    {
        return array_map(static fn ($t) => $t->getName(), $strain->getTerpenes()->getValues());
    }

    public function testImportsAndTidiesTheSheet(): void
    {
        $report = $this->importer()->import($this->csv, 'WEED.csv', false)->jsonSerialize();

        self::assertSame(5, $report['created'], 'duplicate Mango Sherbet row is merged');
        self::assertSame(['Northfield', 'Harbour', 'Oakridge', 'Hillside', 'Seaview'], $report['newBrands']);
        self::assertSame([3, 5, 6], array_column($report['warnings'], 'row'), 'swap, duplicate, repeated genetics');

        $lemon = $this->strain('Citrus Haze');
        self::assertSame('Terrible', $lemon->getARating()->getLabel());
        self::assertSame('Nice', $lemon->getMRating()->getLabel());
        self::assertSame('28.0', $lemon->getThcPercent());
        self::assertSame('80.00', $lemon->getPrice());
        self::assertSame(['Caryophyllene', 'Limonene', 'Myrcene', 'Terpinolene'], $this->terpeneNames($lemon));

        $widow = $this->strain('Orange Peel');
        self::assertSame('Orange Bud x White Widow', $widow->getGenetics(), 'swapped columns are put back');
        self::assertSame(['Caryophyllene', 'Limonene', 'Myrcene', 'Pinene'], $this->terpeneNames($widow));
        self::assertSame('Indica', $widow->getType()->getName());
        self::assertSame('Harbour', $widow->getBrand()->getName());

        $glue = $this->strain('Sticky Pine');
        self::assertNull($glue->getGenetics(), 'genetics that just repeat the terpenes are dropped');

        $amnesia = $this->strain('Morning Haze');
        self::assertNull($amnesia->getGenetics());
        self::assertSame([], $this->terpeneNames($amnesia));
        self::assertSame('49.50', $amnesia->getPrice());
        self::assertSame('Fantastic', $amnesia->getARating()->getLabel());
        self::assertSame('Fantastic', $amnesia->getMRating()->getLabel());
    }

    public function testReimportUpdatesInsteadOfDuplicating(): void
    {
        $this->importer()->import($this->csv, 'WEED.csv', false);
        $report = $this->importer()->import($this->csv, 'WEED.csv', false)->jsonSerialize();

        self::assertSame(0, $report['created']);
        self::assertSame(5, $report['updated']);
        self::assertSame(5, $this->entityManager->getRepository(Strain::class)->count([]));
    }

    public function testDryRunSavesNothing(): void
    {
        $report = $this->importer()->import($this->csv, 'WEED.csv', true)->jsonSerialize();

        self::assertTrue($report['dryRun']);
        self::assertSame(5, $report['created']);
        self::assertSame(0, $this->entityManager->getRepository(Strain::class)->count([]));
    }

    public function testRejectsSheetWithoutRequiredColumns(): void
    {
        file_put_contents($this->csv, "Name,Colour\nFoo,Green\n");

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('"Brand" and "Strain"');
        $this->importer()->import($this->csv, 'other.csv', false);
    }

    public function testReadsXlsxWithSharedStrings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Brand</t></si><si><t>Strain</t></si><si><r><t>4c </t></r><r><t>Labs</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2"><v>28</v></c><c r="B2" t="inlineStr"><is><t>Mac1</t></is></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $rows = (new SpreadsheetReader())->read($path, 'test.xlsx');
        unlink($path);

        self::assertSame([['Brand', 'Strain'], ['4c Labs', 'Mac1', '28']], $rows);
    }
}

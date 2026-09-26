<?php

namespace App\Tests\Import;

use App\Import\CellParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cell formats as they appear in the original strain spreadsheet.
 */
class CellParserTest extends TestCase
{
    public static function terpeneCells(): iterable
    {
        yield 'plain list' => ['Caryophyllene Limonene & Terpinolene', ['Caryophyllene', 'Limonene', 'Terpinolene']];
        yield 'beta/d prefixes' => ['Selinadiene, Beta-Caryophyllene & D-Limonene', ['Selinadiene', 'Caryophyllene', 'Limonene']];
        yield 'typo caryophllene' => ['Trans-Caryophllene, Terpinolene & Limonene', ['Caryophyllene', 'Terpinolene', 'Limonene']];
        yield 'one-l caryophylene' => ['Caryophylene Limonene & Humulene', ['Caryophyllene', 'Limonene', 'Humulene']];
        yield 'double-r carryophyllene' => ['Limonene & Myrcene & Carryophyllene & Terpinolene', ['Limonene', 'Myrcene', 'Caryophyllene', 'Terpinolene']];
        yield 'farnasene + limonen' => ['Myrcene Limonen& Caryophyllene', ['Myrcene', 'Limonene', 'Caryophyllene']];
        yield 'liimonene + shouty ocimene' => ['Caryophyllene & Liimonene & Farnasene & Pinene & OCIMENE', ['Caryophyllene', 'Limonene', 'Farnesene', 'Pinene', 'Ocimene']];
        yield 'selinadine typo' => ['D-Limonene Selinadine & Beta Caryophyllene', ['Limonene', 'Selinadiene', 'Caryophyllene']];
        yield 'bergamotene misspelt' => ['Myrcene Limonene & Trans a Bergemonte', ['Myrcene', 'Limonene', 'Bergamotene']];
        yield 'D- with a space' => ['D- Limonen Beta Myrcene & Beta Caryophyllene', ['Limonene', 'Myrcene', 'Caryophyllene']];
        yield 'unknown' => ['Unknown Terps ', []];
        yield 'empty' => [null, []];
    }

    #[DataProvider('terpeneCells')]
    public function testTerpenesAreCanonicalisedInOrder(?string $cell, array $expected): void
    {
        $parsed = CellParser::terpenes($cell);

        self::assertSame($expected, $parsed['names']);
        self::assertSame([], $parsed['leftover']);
    }

    public function testUnrecognisedTerpeneWordsAreReported(): void
    {
        $parsed = CellParser::terpenes('Limonene Myrcene Caryophyllene & Betas');

        self::assertSame(['Limonene', 'Myrcene', 'Caryophyllene'], $parsed['names']);
        self::assertSame(['betas'], $parsed['leftover']);
    }

    public function testGeneticsTextIsNotATerpeneList(): void
    {
        self::assertFalse(CellParser::isTerpeneList('Orange Bud x White Widow'));
        self::assertFalse(CellParser::isTerpeneList('Freezer Burn & Lemon Fire'));
        self::assertTrue(CellParser::isTerpeneList('Limonene & Myrcene & Caryophyllene & Pinene'));
    }

    public static function ratingCells(): iterable
    {
        yield 'shared' => ['Fantastic', 'Fantastic', 'Fantastic'];
        yield 'typo' => ['Fantatic', 'Fantastic', 'Fantastic'];
        yield 'mids' => ['Mids', 'Mids', 'Mids'];
        yield 'split' => ['Terrible for A Nice for M', 'Terrible', 'Nice'];
        yield 'split, other order' => ['Terrible for A Fantastic for M', 'Terrible', 'Fantastic'];
        yield 'blank' => [null, null, null];
        yield 'nonsense' => ['meh', null, null];
    }

    #[DataProvider('ratingCells')]
    public function testRatingsSplitPerPerson(?string $cell, ?string $a, ?string $m): void
    {
        self::assertSame(['a' => $a, 'm' => $m], CellParser::ratings($cell));
    }

    public function testPricesUseColonAsDecimalPoint(): void
    {
        self::assertSame('62.50', CellParser::price('£62:50'));
        self::assertSame('75.00', CellParser::price('£75'));
        self::assertSame('59.00', CellParser::price('59'));
        self::assertNull(CellParser::price(null));
        self::assertNull(CellParser::price('n/a'));
    }

    public function testThc(): void
    {
        self::assertSame('28.0', CellParser::thc('28'));
        self::assertSame('27.5', CellParser::thc('27.5%'));
        self::assertNull(CellParser::thc('150'));
        self::assertNull(CellParser::thc(null));
    }

    public function testTypeSynonymsCollapse(): void
    {
        self::assertSame('Sativa Dominant', CellParser::type('Sativa Dom '));
        self::assertSame('Sativa Dominant', CellParser::type('Sativa Dominant Hybrid'));
        self::assertSame('Indica Dominant', CellParser::type('Indica Dominant Hybrid'));
        self::assertSame('Indica', CellParser::type('indica'));
        self::assertSame('Ruderalis', CellParser::type('ruderalis'));
    }

    public function testPlaceholderGeneticsBecomeBlank(): void
    {
        self::assertNull(CellParser::genetics('Unknown Lineage '));
        self::assertNull(CellParser::genetics('Proprietary lineage'));
        self::assertSame('MK Ultra & East Coast Sour Diesel', CellParser::genetics(' MK Ultra & East Coast Sour Diesel'));
    }
}

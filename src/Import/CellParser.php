<?php

namespace App\Import;

/**
 * Turns the free-typed cells of the original strain spreadsheet into clean,
 * canonical values. Pure functions only - no database access - so every
 * quirk found in the real sheet is covered by a unit test.
 */
final class CellParser
{
    /**
     * Canonical terpene name => regex matching every spelling seen on
     * packaging and in the sheet ("Trans-Caryophllene", "D- Limonen",
     * "Farnasene", ...). Beta-/alpha-/trans-/D- prefixes are dropped: the
     * app records which terpene, not which isomer.
     */
    public const TERPENES = [
        'Caryophyllene' => '/car+yoph?y?l+ene\w*/',
        'Limonene' => '/li+monen\w*/',
        'Myrcene' => '/myrcene\w*/',
        'Terpinolene' => '/terpinolene\w*/',
        'Ocimene' => '/ocimene\w*/',
        'Linalool' => '/linalool\w*/',
        'Humulene' => '/humulene\w*/',
        'Farnesene' => '/farn[ae]sene\w*/',
        'Pinene' => '/\bpinene\w*/',
        'Selinadiene' => '/selinadi\w*/',
        'Eucalyptol' => '/eucalyptol\w*/',
        'Bergamotene' => '/berg[ae]mo\w*/',
        'Bisabolol' => '/bisabolol\w*/',
        'Nerolidol' => '/nerolidol\w*/',
        'Valencene' => '/valencene\w*/',
        'Geraniol' => '/geraniol\w*/',
    ];

    /** Words that qualify a terpene name rather than being one. */
    private const TERPENE_NOISE = ['beta', 'alpha', 'trans', 'd', 'a', 'b', 'and'];

    /** Lower-cased sheet value => canonical type name. */
    public const TYPES = [
        'indica' => 'Indica',
        'indica dom' => 'Indica Dominant',
        'indica dominant' => 'Indica Dominant',
        'indica dominant hybrid' => 'Indica Dominant',
        'indica hybrid' => 'Indica Hybrid',
        'hybrid' => 'Hybrid',
        'balanced hybrid' => 'Balanced Hybrid',
        'sativa hybrid' => 'Sativa Hybrid',
        'sativa dom' => 'Sativa Dominant',
        'sativa dominant' => 'Sativa Dominant',
        'sativa dominant hybrid' => 'Sativa Dominant',
        'sativa' => 'Sativa',
    ];

    public static function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return '' === $value ? null : $value;
    }

    /**
     * @return array{names: list<string>, leftover: list<string>} canonical
     *     terpene names in the order they appear, plus any words that were
     *     not recognised (reported back as import warnings)
     */
    public static function terpenes(?string $cell): array
    {
        $text = mb_strtolower(self::clean($cell) ?? '');
        if ('' === $text || str_contains($text, 'unknown')) {
            return ['names' => [], 'leftover' => []];
        }

        $found = [];
        $unmatched = $text;
        foreach (self::TERPENES as $name => $pattern) {
            // Positions come from the untouched text so the order is the
            // order they were written in.
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $found[$name] = $m[0][1];
                $unmatched = preg_replace($pattern, ' ', $unmatched);
            }
        }
        asort($found);

        $leftover = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $unmatched) ?: [],
            static fn (string $word): bool => '' !== $word && !\in_array($word, self::TERPENE_NOISE, true),
        ));

        return ['names' => array_keys($found), 'leftover' => $leftover];
    }

    /** True when a cell is nothing but terpene names - i.e. it isn't genetics. */
    public static function isTerpeneList(?string $cell): bool
    {
        $parsed = self::terpenes($cell);

        return \count($parsed['names']) >= 2 && [] === $parsed['leftover'];
    }

    /**
     * Splits a rating cell into Anarlia's and Martyn's ratings. A plain
     * value ("Fantastic") applies to both; "Terrible for A Nice for M"
     * gives each person their own.
     *
     * @return array{a: ?string, m: ?string} canonical rating labels
     */
    public static function ratings(?string $cell): array
    {
        $text = mb_strtolower(self::clean($cell) ?? '');
        if ('' === $text) {
            return ['a' => null, 'm' => null];
        }

        if (preg_match_all('/([a-z]+)\s+for\s+([am])\b/', $text, $matches, PREG_SET_ORDER)) {
            $split = ['a' => null, 'm' => null];
            foreach ($matches as [, $word, $who]) {
                $split[$who] = self::ratingLabel($word);
            }

            return $split;
        }

        $label = self::ratingLabel($text);

        return ['a' => $label, 'm' => $label];
    }

    public static function ratingLabel(string $word): ?string
    {
        $word = mb_strtolower(trim($word));

        return match (true) {
            str_starts_with($word, 'fanta') => 'Fantastic',
            str_starts_with($word, 'nice') => 'Nice',
            str_starts_with($word, 'mid') => 'Mids',
            str_starts_with($word, 'terri') => 'Terrible',
            default => null,
        };
    }

    /** "£62:50" / "62.5" / 75 => "62.50"; anything unparseable => null. */
    public static function price(string|int|float|null $cell): ?string
    {
        if (null === $cell || '' === $cell) {
            return null;
        }
        $text = str_replace([':', ','], ['.', ''], (string) $cell);
        if (!preg_match('/\d+(\.\d+)?/', $text, $m)) {
            return null;
        }

        return number_format((float) $m[0], 2, '.', '');
    }

    /** "28" / "28%" / 27.5 => "28.0"; anything unparseable => null. */
    public static function thc(string|int|float|null $cell): ?string
    {
        if (null === $cell || '' === $cell || !preg_match('/\d+(\.\d+)?/', (string) $cell, $m)) {
            return null;
        }
        $value = (float) $m[0];

        return $value >= 0 && $value <= 100 ? number_format($value, 1, '.', '') : null;
    }

    public static function type(?string $cell): ?string
    {
        $text = self::clean($cell);
        if (null === $text) {
            return null;
        }

        return self::TYPES[mb_strtolower($text)] ?? ucwords(mb_strtolower($text));
    }

    /** "Unknown Lineage" / "Proprietary lineage" carry no information. */
    public static function genetics(?string $cell): ?string
    {
        $text = self::clean($cell);
        if (null === $text || preg_match('/^(unknown|proprietary)\s+lineage$/i', $text)) {
            return null;
        }

        return $text;
    }
}

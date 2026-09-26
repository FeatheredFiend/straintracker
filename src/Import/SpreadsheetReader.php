<?php

namespace App\Import;

/**
 * Reads the first worksheet of an .xlsx file (or a .csv) into rows of
 * strings. Deliberately minimal - cell values only, no formulas, styles or
 * dates - which is all a strain list needs, and avoids pulling in
 * PhpSpreadsheet for one import screen.
 */
final class SpreadsheetReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return list<list<?string>> every row, header first, blank rows dropped
     */
    public function read(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $rows = match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'csv' => $this->readCsv($path),
            default => throw new ImportException('Upload an .xlsx or .csv file.'),
        };

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => [] !== array_filter($row, static fn (?string $v): bool => null !== $v && '' !== trim($v)),
        ));
    }

    /** @return list<list<?string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new ImportException('Could not open the uploaded file.');
        }

        $rows = [];
        while (false !== ($row = fgetcsv($handle, escape: ''))) {
            // Strip a UTF-8 BOM that Excel adds to CSV exports.
            if ([] === $rows && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }
            $rows[] = array_map(static fn ($v): ?string => null === $v ? null : (string) $v, $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<list<?string>> */
    private function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw new ImportException('That file is not a valid .xlsx workbook.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName($this->firstSheetPath($zip));
            if (false === $sheetXml) {
                throw new ImportException('The workbook has no worksheets.');
            }

            $sheet = $this->loadXml($sheetXml);
            $rows = [];
            foreach ($sheet->children(self::NS)->sheetData->row as $rowXml) {
                $row = [];
                foreach ($rowXml->children(self::NS)->c as $cell) {
                    $attributes = $cell->attributes();
                    $index = $this->columnIndex((string) $attributes['r']);
                    $row[$index] = $this->cellValue($cell, (string) $attributes['t'], $sharedStrings);
                }
                if ([] === $row) {
                    continue;
                }
                $filled = array_fill(0, max(array_keys($row)) + 1, null);
                $rows[] = array_replace($filled, $row);
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(\SimpleXMLElement $cell, string $type, array $sharedStrings): ?string
    {
        $children = $cell->children(self::NS);

        return match ($type) {
            's' => $sharedStrings[(int) $children->v] ?? null,
            'inlineStr' => $this->richText($children->is),
            'b' => '1' === (string) $children->v ? 'TRUE' : 'FALSE',
            default => isset($children->v) ? (string) $children->v : null,
        };
    }

    /** @return list<string> */
    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (false === $xml) {
            return [];
        }

        $strings = [];
        foreach ($this->loadXml($xml)->children(self::NS)->si as $item) {
            $strings[] = $this->richText($item);
        }

        return $strings;
    }

    /** Joins every <t> run, so partially-formatted cells come through whole. */
    private function richText(\SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('m', self::NS);

        return implode('', array_map('strval', $node->xpath('.//m:t') ?: []));
    }

    private function firstSheetPath(\ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (false === $workbook || false === $rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheet = $this->loadXml($workbook)->children(self::NS)->sheets->sheet[0] ?? null;
        $relId = $sheet ? (string) $sheet->attributes(self::REL_NS)['id'] : '';

        foreach ($this->loadXml($rels)->children() as $relationship) {
            if ((string) $relationship['Id'] === $relId) {
                $target = ltrim((string) $relationship['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function columnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/', $cellRef, $m);
        $index = 0;
        foreach (str_split($m[0] ?? 'A') as $letter) {
            $index = $index * 26 + (\ord($letter) - 64);
        }

        return $index - 1;
    }

    private function loadXml(string $xml): \SimpleXMLElement
    {
        $element = simplexml_load_string($xml, options: LIBXML_NONET);
        if (false === $element) {
            throw new ImportException('The workbook contains unreadable XML.');
        }

        return $element;
    }
}

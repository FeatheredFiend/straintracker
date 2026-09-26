<?php

namespace App\Api;

use App\Entity\Batch;
use App\Entity\Strain;

/**
 * Applies a submitted list of batches to a strain: rows with a known id
 * update that batch, rows without one add a batch, and batches missing
 * from the list are removed (orphan removal deletes them on flush).
 *
 * Validation is done here rather than with constraints because errors
 * are reported per row - "batches.2.date" - so the form can put them
 * next to the right inputs, and duplicate numbers can only be spotted by
 * looking at the whole list.
 */
final class BatchList
{
    /**
     * @return array<string, string> errors keyed "batches.{row}.{field}"
     */
    public static function apply(Strain $strain, mixed $rows): array
    {
        if (!\is_array($rows)) {
            return ['batches' => 'Batches must be a list.'];
        }

        $existing = [];
        foreach ($strain->getBatches() as $batch) {
            $existing[$batch->getId()] = $batch;
        }

        $errors = [];
        $kept = [];
        $numbersSeen = [];

        foreach (array_values($rows) as $i => $row) {
            $row = \is_array($row) ? $row : [];
            $number = trim((string) ($row['batchNumber'] ?? ''));
            $date = self::date($row['date'] ?? null);

            $key = mb_strtolower($number);
            if ('' === $number) {
                $errors["batches.$i.batchNumber"] = 'Enter the batch number, or remove this batch.';
            } elseif (mb_strlen($number) > Batch::MAX_NUMBER_LENGTH) {
                $errors["batches.$i.batchNumber"] = sprintf('Keep it under %d characters.', Batch::MAX_NUMBER_LENGTH);
            } elseif (isset($numbersSeen[$key])) {
                $errors["batches.$i.batchNumber"] = 'This batch number is already listed.';
            }
            $numbersSeen[$key] = true;

            if (null === $date) {
                $errors["batches.$i.date"] = 'Enter a valid date.';
            }

            $id = $row['id'] ?? null;
            $batch = is_numeric($id) && isset($existing[(int) $id]) ? $existing[(int) $id] : new Batch();
            $batch->setBatchNumber($number)->setDate($date);
            $strain->addBatch($batch);
            $kept[spl_object_id($batch)] = true;
        }

        foreach ($strain->getBatches()->getValues() as $batch) {
            if (!isset($kept[spl_object_id($batch)])) {
                $strain->removeBatch($batch);
            }
        }

        return $errors;
    }

    /** Only an exact, real "YYYY-MM-DD" (what <input type="date"> sends). */
    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}

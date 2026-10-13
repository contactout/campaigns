<?php

namespace App\Support;

use Generator;
use RuntimeException;

class CsvRows
{
    /**
     * Read the header row of the CSV file at the given path.
     *
     * @return array<int, string>
     */
    public static function header(string $path): array
    {
        foreach (self::read($path) as $row) {
            return $row;
        }

        return [];
    }

    /**
     * Yield each non-blank row keyed by its 1-based line number, header included.
     *
     * @return Generator<int, array<int, string>>
     */
    public static function read(string $path): Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file [{$path}].");
        }

        try {
            $line = 0;

            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;

                if ($row === [null]) {
                    continue;
                }

                $row = array_map(fn (?string $value): string => trim((string) $value), $row);

                if ($line === 1) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]) ?? $row[0];
                }

                yield $line => $row;
            }
        } finally {
            fclose($handle);
        }
    }
}

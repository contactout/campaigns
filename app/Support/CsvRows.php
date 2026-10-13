<?php

namespace App\Support;

use Generator;
use RuntimeException;

class CsvRows
{
    /**
     * Read the header (first non-blank) row of the CSV file at the given path.
     *
     * @return array<int, string>
     */
    public static function header(string $path): array
    {
        foreach (self::read($path) as [$row]) {
            return $row;
        }

        return [];
    }

    /**
     * Yield each non-blank row starting at the given byte offset, keyed by its 1-based line number.
     *
     * Each value holds the trimmed cells and the byte offset just after the row,
     * so reading can resume from there. A leading UTF-8 byte order mark is skipped.
     *
     * @return Generator<int, array{0: array<int, string>, 1: int}>
     */
    public static function read(string $path, int $offset = 0, int $line = 0): Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file [{$path}].");
        }

        try {
            if ($offset > 0) {
                fseek($handle, $offset);
            } elseif (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;

                if ($row === [null]) {
                    continue;
                }

                yield $line => [
                    array_map(fn (?string $value): string => trim((string) $value), $row),
                    (int) ftell($handle),
                ];
            }
        } finally {
            fclose($handle);
        }
    }
}

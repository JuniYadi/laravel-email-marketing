<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class CsvUploadedFileReader
{
    /**
     * @return list<list<string|null>>
     */
    public static function readRows(UploadedFile $uploadedFile): array
    {
        $contents = $uploadedFile->get();

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', rtrim($contents));

        if (! is_array($lines) || $lines === []) {
            return [];
        }

        return array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', '\\'),
            $lines,
        );
    }
}

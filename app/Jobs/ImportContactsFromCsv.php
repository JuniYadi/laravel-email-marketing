<?php

namespace App\Jobs;

use App\Support\Contacts\CsvContactImporter;
use App\Support\CsvUploadedFileReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ImportContactsFromCsv implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $path,
        public string $disk,
        public array $selectedGroupIds = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CsvContactImporter $importer): void
    {
        if (! Storage::disk($this->disk)->exists($this->path)) {
            return;
        }

        $contents = Storage::disk($this->disk)->get($this->path);
        $rows = CsvUploadedFileReader::readRowsFromContents($contents);

        if ($rows === [] || ! isset($rows[0])) {
            Storage::disk($this->disk)->delete($this->path);

            return;
        }

        $importer->importRows($rows, $this->selectedGroupIds);
        Storage::disk($this->disk)->delete($this->path);
    }
}

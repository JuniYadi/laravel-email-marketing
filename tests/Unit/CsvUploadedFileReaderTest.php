<?php

use App\Support\CsvUploadedFileReader;
use Illuminate\Http\UploadedFile;

it('reads csv rows even when getRealPath is not a local readable path', function () {
    $csv = "email,firstName,lastName\n";
    $csv .= "remote@example.com,Remote,User\n";

    $path = tempnam(sys_get_temp_dir(), 'csv-upload-');

    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    file_put_contents($path, $csv);

    $uploadedFile = new class($path) extends UploadedFile
    {
        public function __construct(string $path)
        {
            parent::__construct($path, 'contacts.csv', 'text/csv', null, true);
        }

        public function getRealPath(): string
        {
            return 'livewire-tmp/non-local-file.csv';
        }
    };

    $rows = CsvUploadedFileReader::readRows($uploadedFile);

    expect($rows)->toBe([
        ['email', 'firstName', 'lastName'],
        ['remote@example.com', 'Remote', 'User'],
    ]);

    @unlink($path);
});

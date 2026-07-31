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

it('converts non-utf8 bytes to valid utf-8', function () {
    $csv = "email,firstName,lastName\n";
    $csv .= "dahliyahja@gmail.com,Dahliyah Ja\x92Anih,\n";

    $path = tempnam(sys_get_temp_dir(), 'csv-upload-');

    if ($path === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    file_put_contents($path, $csv);

    $uploadedFile = new UploadedFile($path, 'contacts.csv', 'text/csv', null, true);

    $rows = CsvUploadedFileReader::readRows($uploadedFile);

    expect(mb_check_encoding($rows[1][1], 'UTF-8'))->toBeTrue()
        ->and($rows[1][1])->toBe("Dahliyah Ja\u{2019}Anih");

    @unlink($path);
});

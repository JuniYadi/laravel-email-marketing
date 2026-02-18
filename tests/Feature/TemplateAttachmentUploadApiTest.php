<?php

use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
    config()->set('filesystems.default', 's3');
});

test('presign endpoint returns upload payload for valid files', function (): void {
    $mockedDisk = \Mockery::mock(FilesystemAdapter::class);
    $mockedDisk->shouldReceive('temporaryUploadUrl')
        ->once()
        ->andReturn([
            'url' => 'https://example-bucket.s3.amazonaws.com/template-attachments/fake.pdf',
            'headers' => ['Content-Type' => 'application/pdf'],
        ]);

    Storage::shouldReceive('disk')
        ->once()
        ->with('s3')
        ->andReturn($mockedDisk);

    $response = $this->postJson(route('templates.attachments.presign'), [
        'files' => [
            [
                'name' => 'proposal.pdf',
                'size' => 1024,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'uploads' => [
                '*' => ['name', 'size', 'mime_type', 'path', 'disk', 'upload_url', 'upload_headers', 'method'],
            ],
        ]);

    expect($response->json('uploads.0.path'))->toStartWith('template-attachments/');
});

test('presign endpoint validates attachment extension', function (): void {
    $response = $this->postJson(route('templates.attachments.presign'), [
        'files' => [
            [
                'name' => 'script.exe',
                'size' => 1024,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    $response->assertUnprocessable()->assertInvalid(['files.0.name']);
});

test('finalize endpoint returns normalized attachment metadata', function (): void {
    Storage::disk('local')->put('template-attachments/manual-upload.pdf', 'abc');

    $response = $this->postJson(route('templates.attachments.finalize'), [
        'uploads' => [
            [
                'name' => 'manual-upload.pdf',
                'size' => 3,
                'mime_type' => 'application/pdf',
                'path' => 'template-attachments/manual-upload.pdf',
                'disk' => 'local',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'attachments' => [
                '*' => ['id', 'name', 'path', 'disk', 'size', 'mime_type', 'uploaded_at'],
            ],
        ]);

    expect($response->json('attachments.0.path'))->toBe('template-attachments/manual-upload.pdf');
});

test('finalize endpoint rejects missing object', function (): void {
    $response = $this->postJson(route('templates.attachments.finalize'), [
        'uploads' => [
            [
                'name' => 'missing.pdf',
                'size' => 3,
                'mime_type' => 'application/pdf',
                'path' => 'template-attachments/missing.pdf',
                'disk' => 'local',
            ],
        ],
    ]);

    $response->assertUnprocessable()->assertInvalid(['uploads']);
});

test('delete endpoint removes stored attachment', function (): void {
    Storage::disk('local')->put('template-attachments/to-delete.pdf', 'abc');

    $this->deleteJson(route('templates.attachments.delete'), [
        'path' => 'template-attachments/to-delete.pdf',
        'disk' => 'local',
    ])->assertOk();

    Storage::disk('local')->assertMissing('template-attachments/to-delete.pdf');
});

test('cleanup-unsaved endpoint deletes all provided unsaved attachments', function (): void {
    Storage::disk('local')->put('template-attachments/unsaved-a.pdf', 'abc');
    Storage::disk('local')->put('template-attachments/unsaved-b.pdf', 'abc');

    $this->postJson(route('templates.attachments.cleanup-unsaved'), [
        'attachments' => [
            ['path' => 'template-attachments/unsaved-a.pdf', 'disk' => 'local'],
            ['path' => 'template-attachments/unsaved-b.pdf', 'disk' => 'local'],
        ],
    ])->assertOk();

    Storage::disk('local')->assertMissing('template-attachments/unsaved-a.pdf');
    Storage::disk('local')->assertMissing('template-attachments/unsaved-b.pdf');
});

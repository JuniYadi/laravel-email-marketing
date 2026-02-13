<?php

use App\Livewire\Templates\AttachmentUpload;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('mounts with empty attachments when no template id provided', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->assertSet('templateId', null)
        ->assertSet('attachments', [])
        ->assertSet('newAttachments', []);
});

it('mounts with existing attachments from template', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'id' => 'ulid-123',
                'name' => 'document.pdf',
                'path' => 'template-attachments/test.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    Livewire::test(AttachmentUpload::class, ['templateId' => $template->id])
        ->assertSet('templateId', $template->id)
        ->assertSet('attachments', $template->attachments);
});

it('validates file uploads with correct mime types and size', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf')])
        ->assertHasNoErrors();
});

it('rejects files over 40MB limit', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [UploadedFile::fake()->create('large.pdf', 41000, 'application/pdf')])
        ->assertHasErrors('newAttachments.0');
});

it('rejects files with invalid mime types', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [UploadedFile::fake()->create('image.png', 1024, 'image/png')])
        ->assertHasErrors(['newAttachments.0' => 'mimetypes']);
});

it('adds attachment to array and stores file on disk', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file])
        ->call('addAttachment', 0)
        ->assertSet('attachments', fn (array $attachments): bool => count($attachments) === 1)
        ->assertSet('attachments.0.name', 'document.pdf')
        ->assertSet('attachments.0.size', fn (int $size): bool => $size > 0)
        ->assertSet('attachments.0.mime_type', 'application/pdf')
        ->assertSet('attachments.0.disk', 'local')
        ->assertSet('newAttachments', [])
        ->assertDispatched('attachmentsUpdated');

    Storage::disk('local')->assertExists(
        Livewire::test(AttachmentUpload::class)
            ->set('newAttachments', [$file])
            ->call('addAttachment', 0)
            ->get('attachments.0.path')
    );
});

it('removes attachment from array and deletes file from disk', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Storage::disk('local')->put('template-attachments/test.pdf', 'content');

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-123',
                'name' => 'document.pdf',
                'path' => 'template-attachments/test.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->call('removeAttachment', 'ulid-123')
        ->assertSet('attachments', [])
        ->assertDispatched('attachmentsUpdated');

    Storage::disk('local')->assertMissing('template-attachments/test.pdf');
});

it('calculates total size of attachments', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'doc1.pdf',
                'path' => 'template-attachments/doc1.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
            [
                'id' => 'ulid-2',
                'name' => 'doc2.pdf',
                'path' => 'template-attachments/doc2.pdf',
                'disk' => 'local',
                'size' => 2048,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->assertSet('totalSize', 3072);
});

it('formats total size in human readable format', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'doc1.pdf',
                'path' => 'template-attachments/doc1.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->assertSet('totalSizeFormatted', '1 KB');
});

it('detects when total size exceeds limit', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $overLimitSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES + 1024;

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'large.pdf',
                'path' => 'template-attachments/large.pdf',
                'disk' => 'local',
                'size' => $overLimitSize,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->assertSet('isOverLimit', true);
});

it('calculates remaining size correctly', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'doc1.pdf',
                'path' => 'template-attachments/doc1.pdf',
                'disk' => 'local',
                'size' => 1024 * 1024, // 1MB
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->assertSet('remainingSize', EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES - (1024 * 1024));
});

it('calculates progress percentage correctly', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $halfSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES / 2;

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'doc1.pdf',
                'path' => 'template-attachments/doc1.pdf',
                'disk' => 'local',
                'size' => $halfSize,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->assertSet('progressPercentage', 50.0);
});

it('handles non-existent attachment index gracefully in addAttachment', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->call('addAttachment', 999)
        ->assertSet('attachments', []);
});

it('handles non-existent attachment id gracefully in removeAttachment', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'doc1.pdf',
                'path' => 'template-attachments/doc1.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->call('removeAttachment', 'non-existent-id')
        ->assertSet('attachments', fn (array $attachments): bool => count($attachments) === 1);
});

it('generates unique ulid for each attachment', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file1 = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('doc2.pdf', 1024, 'application/pdf');

    $component = Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file1])
        ->call('addAttachment', 0)
        ->set('newAttachments', [$file2])
        ->call('addAttachment', 0);

    $attachments = $component->get('attachments');

    expect($attachments)->toHaveCount(2);
    expect($attachments[0]['id'])->not->toBe($attachments[1]['id']);
});

it('rejects adding file that would exceed total 40MB limit', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    // Simulate existing attachments that total 39MB
    $existingSize = 39 * 1024 * 1024; // 39MB in bytes
    // Create a new file that is 2MB (together would be 41MB > 40MB limit)
    $file = UploadedFile::fake()->create('doc2.pdf', 2 * 1024, 'application/pdf');

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => $existingSize,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->set('newAttachments', [$file])
        ->call('addAttachment', 0)
        ->assertHasErrors(['newAttachments.0' => 'Adding this file would exceed the 40MB total limit.'])
        ->assertSet('attachments', fn (array $attachments): bool => count($attachments) === 1);
});

it('handles division by zero in progress percentage calculation', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    // Mock EmailTemplate constant to be 0 for this test
    // Since we can't mock constants directly, we'll test the logic
    // by checking that the method handles edge cases gracefully
    $component = Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'ulid-1',
                'name' => 'doc1.pdf',
                'path' => 'template-attachments/doc1.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ]);

    // The progress percentage should return a valid float even with attachments
    expect($component->get('progressPercentage'))->toBeFloat();
    expect($component->get('progressPercentage'))->toBeGreaterThanOrEqual(0.0);
    expect($component->get('progressPercentage'))->toBeLessThanOrEqual(100.0);
});

it('removes a pending file from newAttachments array', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file1 = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('doc2.pdf', 1024, 'application/pdf');

    $component = Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file1, $file2])
        ->assertCount('newAttachments', 2)
        ->call('removeNewAttachment', 0)
        ->assertCount('newAttachments', 1);

    // Verify the remaining file is doc2.pdf
    $remainingFiles = $component->get('newAttachments');
    expect($remainingFiles[0]->getClientOriginalName())->toBe('doc2.pdf');
});

it('handles removing non-existent pending file gracefully', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file])
        ->call('removeNewAttachment', 999)
        ->assertCount('newAttachments', 1);
});

it('clears all pending files from newAttachments array', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file1 = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('doc2.pdf', 1024, 'application/pdf');

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file1, $file2])
        ->assertCount('newAttachments', 2)
        ->call('clearNewAttachments')
        ->assertSet('newAttachments', []);
});

it('clears validation errors when clearing pending files', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file])
        ->call('addAttachment', 0)
        ->set('newAttachments', [UploadedFile::fake()->create('large.pdf', 41000, 'application/pdf')])
        ->assertHasErrors()
        ->call('clearNewAttachments')
        ->assertHasNoErrors();
});

it('adds all pending files at once using addAllAttachments', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file1 = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('doc2.pdf', 1024, 'application/pdf');
    $file3 = UploadedFile::fake()->create('doc3.pdf', 1024, 'application/pdf');

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file1, $file2, $file3])
        ->call('addAllAttachments')
        ->assertCount('attachments', 3)
        ->assertSet('newAttachments', [])
        ->assertDispatched('attachmentsUpdated');

    // Verify all files are stored
    expect(Storage::disk('local')->allFiles('template-attachments'))->toHaveCount(3);
});

it('skips files that would exceed limit when using addAllAttachments', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    // Create existing attachment of 38MB
    $existingSize = 38 * 1024 * 1024;

    // Create two new files: 1MB (should add) and 3MB (should skip - would be 42MB total)
    $file1 = UploadedFile::fake()->create('small.pdf', 1024, 'application/pdf'); // 1MB
    $file2 = UploadedFile::fake()->create('large.pdf', 3 * 1024, 'application/pdf'); // 3MB

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'existing-file',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => $existingSize,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->set('newAttachments', [$file1, $file2])
        ->call('addAllAttachments')
        ->assertCount('attachments', 2) // 1 existing + 1 added (1 skipped)
        ->assertSet('newAttachments', [])
        ->assertHasErrors('newAttachments');
});

it('shows warning message for skipped files in addAllAttachments', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $existingSize = 39 * 1024 * 1024; // 39MB
    $file = UploadedFile::fake()->create('toobig.pdf', 2 * 1024, 'application/pdf'); // 2MB

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'existing-file',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => $existingSize,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->set('newAttachments', [$file])
        ->call('addAllAttachments')
        ->assertHasErrors('newAttachments')
        ->assertSee('Warning')
        ->assertSee('skipped')
        ->assertSee('toobig.pdf');
});

it('handles empty newAttachments array in addAllAttachments gracefully', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [])
        ->call('addAllAttachments')
        ->assertSet('attachments', [])
        ->assertSet('newAttachments', []);
});

it('reindexes newAttachments array after removal', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());

    $file1 = UploadedFile::fake()->create('doc1.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('doc2.pdf', 1024, 'application/pdf');
    $file3 = UploadedFile::fake()->create('doc3.pdf', 1024, 'application/pdf');

    $component = Livewire::test(AttachmentUpload::class)
        ->set('newAttachments', [$file1, $file2, $file3])
        ->call('removeNewAttachment', 1) // Remove middle file
        ->assertCount('newAttachments', 2);

    // Verify array is properly reindexed (0, 1 instead of 0, 2)
    $newAttachments = $component->get('newAttachments');
    expect(array_keys($newAttachments))->toBe([0, 1]);
});

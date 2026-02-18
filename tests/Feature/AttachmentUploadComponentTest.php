<?php

use App\Livewire\Templates\AttachmentUpload;
use App\Models\EmailTemplate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('initializes with empty attachments by default', function (): void {
    Livewire::test(AttachmentUpload::class)
        ->assertSet('attachments', [])
        ->assertSet('totalSize', 0)
        ->assertSet('isOverLimit', false);
});

test('loads existing attachments when template id is provided', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'id' => 'att-1',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    Livewire::test(AttachmentUpload::class, ['templateId' => $template->id])
        ->assertSet('attachments.0.name', 'existing.pdf')
        ->assertSet('totalSize', 1024);
});

test('appendUploadedAttachments appends new attachment metadata', function (): void {
    Livewire::test(AttachmentUpload::class)
        ->call('appendUploadedAttachments', [
            [
                'id' => 'att-2',
                'name' => 'proposal.pdf',
                'path' => 'template-attachments/proposal.pdf',
                'disk' => 'local',
                'size' => 2048,
                'mime_type' => 'application/pdf',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])
        ->assertCount('attachments', 1)
        ->assertSet('attachments.0.name', 'proposal.pdf')
        ->assertSet('totalSize', 2048);
});

test('appendUploadedAttachments rejects additions that exceed total limit', function (): void {
    $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'att-big',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => $maxSize,
                'mime_type' => 'application/pdf',
            ],
        ])
        ->call('appendUploadedAttachments', [
            [
                'id' => 'att-over',
                'name' => 'over.pdf',
                'path' => 'template-attachments/over.pdf',
                'disk' => 'local',
                'size' => 1,
                'mime_type' => 'application/pdf',
            ],
        ])
        ->assertHasErrors(['attachments']);
});

test('removeUploadedAttachment removes attachment by id', function (): void {
    Livewire::test(AttachmentUpload::class)
        ->set('attachments', [
            [
                'id' => 'att-1',
                'name' => 'first.pdf',
                'path' => 'template-attachments/first.pdf',
                'disk' => 'local',
                'size' => 100,
                'mime_type' => 'application/pdf',
            ],
            [
                'id' => 'att-2',
                'name' => 'second.pdf',
                'path' => 'template-attachments/second.pdf',
                'disk' => 'local',
                'size' => 200,
                'mime_type' => 'application/pdf',
            ],
        ])
        ->call('removeUploadedAttachment', 'att-1')
        ->assertCount('attachments', 1)
        ->assertSet('attachments.0.id', 'att-2')
        ->assertSet('totalSize', 200);
});

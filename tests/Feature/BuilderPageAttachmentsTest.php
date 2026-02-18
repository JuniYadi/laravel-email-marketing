<?php

use App\Livewire\Templates\BuilderPage;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('BuilderPage has attachments property initialized as empty array', function (): void {
    Livewire::test(BuilderPage::class)
        ->assertSet('attachments', []);
});

test('BuilderPage loads existing attachments when mounting with template', function (): void {
    $attachments = [
        [
            'id' => 'att-123',
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 2048,
        ],
    ];

    $template = EmailTemplate::factory()->create([
        'attachments' => $attachments,
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->assertSet('attachments', $attachments);
});

test('BuilderPage initializes empty attachments when mounting without template', function (): void {
    Livewire::test(BuilderPage::class)
        ->assertSet('attachments', []);
});

test('BuilderPage has attachmentsUpdated listener', function (): void {
    $newAttachments = [
        [
            'id' => 'att-456',
            'name' => 'report.pdf',
            'path' => 'attachments/report.pdf',
            'size' => 4096,
        ],
    ];

    // Dispatch the event and verify the component responds
    Livewire::test(BuilderPage::class)
        ->dispatch('attachmentsUpdated', attachments: $newAttachments)
        ->assertSet('attachments', $newAttachments);
});

test('updateAttachments method updates attachments property', function (): void {
    $newAttachments = [
        [
            'id' => 'att-456',
            'name' => 'report.pdf',
            'path' => 'attachments/report.pdf',
            'size' => 4096,
        ],
    ];

    Livewire::test(BuilderPage::class)
        ->call('updateAttachments', $newAttachments)
        ->assertSet('attachments', $newAttachments);
});

test('continueToBuilder validates attachments structure', function (): void {
    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', [
            [
                'id' => 'att-123',
                'name' => 'document.pdf',
                'path' => 'attachments/document.pdf',
                'size' => 2048,
            ],
        ])
        ->call('continueToBuilder')
        ->assertHasNoErrors();
});

test('continueToBuilder validates attachment id is required', function (): void {
    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', [
            [
                'name' => 'document.pdf',
                'path' => 'attachments/document.pdf',
                'size' => 2048,
            ],
        ])
        ->call('continueToBuilder')
        ->assertHasErrors(['attachments.0.id']);
});

test('continueToBuilder validates attachment name is required', function (): void {
    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', [
            [
                'id' => 'att-123',
                'path' => 'attachments/document.pdf',
                'size' => 2048,
            ],
        ])
        ->call('continueToBuilder')
        ->assertHasErrors(['attachments.0.name']);
});

test('continueToBuilder validates attachment path is required', function (): void {
    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', [
            [
                'id' => 'att-123',
                'name' => 'document.pdf',
                'size' => 2048,
            ],
        ])
        ->call('continueToBuilder')
        ->assertHasErrors(['attachments.0.path']);
});

test('continueToBuilder validates attachment size is required', function (): void {
    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', [
            [
                'id' => 'att-123',
                'name' => 'document.pdf',
                'path' => 'attachments/document.pdf',
            ],
        ])
        ->call('continueToBuilder')
        ->assertHasErrors(['attachments.0.size']);
});

test('continueToBuilder validates attachment size does not exceed limit', function (): void {
    $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', [
            [
                'id' => 'att-123',
                'name' => 'huge-file.pdf',
                'path' => 'attachments/huge-file.pdf',
                'size' => $maxSize + 1,
            ],
        ])
        ->call('continueToBuilder')
        ->assertHasErrors(['attachments.0.size']);
});

test('continueToBuilder validates total attachment size does not exceed limit', function (): void {
    $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

    $attachments = [
        [
            'id' => 'att-1',
            'name' => 'file1.pdf',
            'path' => 'attachments/file1.pdf',
            'size' => ($maxSize / 2) + 1,
        ],
        [
            'id' => 'att-2',
            'name' => 'file2.pdf',
            'path' => 'attachments/file2.pdf',
            'size' => ($maxSize / 2) + 1,
        ],
    ];

    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', $attachments)
        ->call('continueToBuilder')
        ->assertHasErrors(['attachments']);
});

test('continueToBuilder saves attachments to new template', function (): void {
    $attachments = [
        [
            'id' => 'att-123',
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 2048,
        ],
    ];

    Livewire::test(BuilderPage::class)
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('theme.content_width', 640)
        ->set('attachments', $attachments)
        ->call('continueToBuilder')
        ->assertHasNoErrors();

    $template = EmailTemplate::query()->where('name', 'Test Template')->first();

    expect($template)->not->toBeNull()
        ->and($template->attachments)->toBe($attachments);
});

test('continueToBuilder updates attachments on existing template', function (): void {
    $oldAttachments = [
        [
            'id' => 'att-old',
            'name' => 'old.pdf',
            'path' => 'attachments/old.pdf',
            'size' => 1024,
        ],
    ];

    $newAttachments = [
        [
            'id' => 'att-new',
            'name' => 'new.pdf',
            'path' => 'attachments/new.pdf',
            'size' => 2048,
        ],
    ];

    $template = EmailTemplate::factory()->create([
        'attachments' => $oldAttachments,
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->set('attachments', $newAttachments)
        ->call('continueToBuilder')
        ->assertHasNoErrors();

    $template->refresh();

    expect($template->attachments)->toBe($newAttachments);
});

test('saveTemplate includes attachments validation', function (): void {
    $attachments = [
        [
            'id' => 'att-123',
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 2048,
        ],
    ];

    $template = EmailTemplate::factory()->create();

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->set('attachments', $attachments)
        ->set('currentStep', 2)
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertRedirect(route('templates.index'));

    $template->refresh();

    expect($template->attachments)->toBe($attachments);
});

test('saveTemplate throws validation exception when total attachment size exceeds limit', function (): void {
    $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

    $attachments = [
        [
            'id' => 'att-1',
            'name' => 'file1.pdf',
            'path' => 'attachments/file1.pdf',
            'size' => ($maxSize / 2) + 1,
        ],
        [
            'id' => 'att-2',
            'name' => 'file2.pdf',
            'path' => 'attachments/file2.pdf',
            'size' => ($maxSize / 2) + 1,
        ],
    ];

    $template = EmailTemplate::factory()->create();

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->set('attachments', $attachments)
        ->set('currentStep', 2)
        ->call('saveTemplate')
        ->assertHasErrors(['attachments']);
});

test('getIsOverAttachmentLimitProperty returns false when under limit', function (): void {
    $attachments = [
        [
            'id' => 'att-123',
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 2048,
        ],
    ];

    $component = Livewire::test(BuilderPage::class)
        ->set('attachments', $attachments);

    expect($component->get('isOverAttachmentLimit'))->toBeFalse();
});

test('getIsOverAttachmentLimitProperty returns true when over limit', function (): void {
    $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

    $attachments = [
        [
            'id' => 'att-123',
            'name' => 'huge-file.pdf',
            'path' => 'attachments/huge-file.pdf',
            'size' => $maxSize + 1,
        ],
    ];

    $component = Livewire::test(BuilderPage::class)
        ->set('attachments', $attachments);

    expect($component->get('isOverAttachmentLimit'))->toBeTrue();
});

test('getIsOverAttachmentLimitProperty calculates total from multiple attachments', function (): void {
    $maxSize = EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES;

    $attachments = [
        [
            'id' => 'att-1',
            'name' => 'file1.pdf',
            'path' => 'attachments/file1.pdf',
            'size' => ($maxSize / 2) + 1,
        ],
        [
            'id' => 'att-2',
            'name' => 'file2.pdf',
            'path' => 'attachments/file2.pdf',
            'size' => ($maxSize / 2) + 1,
        ],
    ];

    $component = Livewire::test(BuilderPage::class)
        ->set('attachments', $attachments);

    expect($component->get('isOverAttachmentLimit'))->toBeTrue();
});

test('getIsOverAttachmentLimitProperty returns false for empty attachments', function (): void {
    $component = Livewire::test(BuilderPage::class)
        ->set('attachments', []);

    expect($component->get('isOverAttachmentLimit'))->toBeFalse();
});

test('cancelEditing removes unsaved attachments for new template flow', function (): void {
    Storage::disk('local')->put('template-attachments/new-unsaved.pdf', 'content');

    Livewire::test(BuilderPage::class)
        ->set('attachments', [
            [
                'id' => 'att-new',
                'name' => 'new-unsaved.pdf',
                'path' => 'template-attachments/new-unsaved.pdf',
                'disk' => 'local',
                'size' => 7,
                'mime_type' => 'application/pdf',
            ],
        ])
        ->call('cancelEditing')
        ->assertRedirect(route('templates.index'));

    Storage::disk('local')->assertMissing('template-attachments/new-unsaved.pdf');
});

test('cancelEditing keeps existing attachment but removes newly uploaded attachment for edit flow', function (): void {
    Storage::disk('local')->put('template-attachments/existing.pdf', 'existing');
    Storage::disk('local')->put('template-attachments/new-upload.pdf', 'new');

    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'id' => 'att-existing',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => 8,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    Livewire::test(BuilderPage::class, ['template' => $template])
        ->set('attachments', [
            [
                'id' => 'att-existing',
                'name' => 'existing.pdf',
                'path' => 'template-attachments/existing.pdf',
                'disk' => 'local',
                'size' => 8,
                'mime_type' => 'application/pdf',
            ],
            [
                'id' => 'att-new',
                'name' => 'new-upload.pdf',
                'path' => 'template-attachments/new-upload.pdf',
                'disk' => 'local',
                'size' => 3,
                'mime_type' => 'application/pdf',
            ],
        ])
        ->call('cancelEditing')
        ->assertRedirect(route('templates.index'));

    Storage::disk('local')->assertExists('template-attachments/existing.pdf');
    Storage::disk('local')->assertMissing('template-attachments/new-upload.pdf');
});

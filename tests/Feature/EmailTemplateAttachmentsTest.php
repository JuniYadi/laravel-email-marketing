<?php

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('email_templates table has attachments column', function (): void {
    $columns = Schema::getColumnListing('email_templates');

    expect($columns)->toContain('attachments');
});

test('attachments column is nullable', function (): void {
    $template = EmailTemplate::factory()->create();

    // Query the database directly to verify the column value
    $result = DB::table('email_templates')
        ->where('id', $template->id)
        ->first();

    expect($result)->toHaveProperty('attachments')
        ->and($result->attachments)->toBeNull();
});

test('attachments column accepts json data', function (): void {
    $attachments = [
        [
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 1024,
            'mime_type' => 'application/pdf',
            'disk' => 'local',
        ],
    ];

    $template = EmailTemplate::factory()->create();

    DB::table('email_templates')
        ->where('id', $template->id)
        ->update(['attachments' => json_encode($attachments)]);

    $template->refresh();

    $stored = json_decode($template->getAttributes()['attachments'], true);

    expect($stored)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($stored[0])
        ->toMatchArray([
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 1024,
            'mime_type' => 'application/pdf',
            'disk' => 'local',
        ]);
});

test('attachments attribute is automatically cast to array', function (): void {
    $attachments = [
        [
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 1024,
            'mime_type' => 'application/pdf',
            'disk' => 'local',
        ],
    ];

    $template = EmailTemplate::factory()->create([
        'attachments' => $attachments,
    ]);

    expect($template->attachments)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($template->attachments[0])
        ->toMatchArray([
            'name' => 'document.pdf',
            'path' => 'attachments/document.pdf',
            'size' => 1024,
            'mime_type' => 'application/pdf',
            'disk' => 'local',
        ]);
});

test('EmailTemplate has correct attachment constants', function (): void {
    expect(EmailTemplate::ALLOWED_ATTACHMENT_MIME_TYPES)
        ->toBeArray()
        ->toContain('application/pdf')
        ->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->toContain('application/msword')
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->toContain('application/vnd.ms-excel')
        ->toContain('application/vnd.openxmlformats-officedocument.presentationml.presentation')
        ->toContain('application/vnd.ms-powerpoint')
        ->and(EmailTemplate::ALLOWED_ATTACHMENT_EXTENSIONS)
        ->toBeArray()
        ->toContain('pdf')
        ->toContain('docx')
        ->toContain('doc')
        ->toContain('xlsx')
        ->toContain('xls')
        ->toContain('pptx')
        ->toContain('ppt')
        ->and(EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_MB)
        ->toBe(40)
        ->and(EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES)
        ->toBe(40 * 1024 * 1024);
});

test('getTotalAttachmentSize returns zero for template without attachments', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => null,
    ]);

    expect($template->getTotalAttachmentSize())->toBe(0);
});

test('getTotalAttachmentSize returns zero for template with empty attachments array', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => [],
    ]);

    expect($template->getTotalAttachmentSize())->toBe(0);
});

test('getTotalAttachmentSize calculates total size correctly for single attachment', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'name' => 'document.pdf',
                'path' => 'attachments/document.pdf',
                'size' => 2048,
                'mime_type' => 'application/pdf',
                'disk' => 'local',
            ],
        ],
    ]);

    expect($template->getTotalAttachmentSize())->toBe(2048);
});

test('getTotalAttachmentSize calculates total size correctly for multiple attachments', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'name' => 'document.pdf',
                'path' => 'attachments/document.pdf',
                'size' => 2048,
                'mime_type' => 'application/pdf',
                'disk' => 'local',
            ],
            [
                'name' => 'spreadsheet.xlsx',
                'path' => 'attachments/spreadsheet.xlsx',
                'size' => 4096,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'disk' => 'local',
            ],
            [
                'name' => 'presentation.pptx',
                'path' => 'attachments/presentation.pptx',
                'size' => 8192,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'disk' => 'local',
            ],
        ],
    ]);

    expect($template->getTotalAttachmentSize())->toBe(14336); // 2048 + 4096 + 8192
});

test('hasAttachments returns false for template without attachments', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => null,
    ]);

    expect($template->hasAttachments())->toBeFalse();
});

test('hasAttachments returns false for template with empty attachments array', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => [],
    ]);

    expect($template->hasAttachments())->toBeFalse();
});

test('hasAttachments returns true for template with attachments', function (): void {
    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'name' => 'document.pdf',
                'path' => 'attachments/document.pdf',
                'size' => 1024,
                'mime_type' => 'application/pdf',
                'disk' => 'local',
            ],
        ],
    ]);

    expect($template->hasAttachments())->toBeTrue();
});

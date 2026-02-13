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

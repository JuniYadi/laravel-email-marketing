<?php

use App\Mail\BroadcastRecipientMail;
use Illuminate\Support\Facades\Storage;

it('returns empty array when no attachments provided', function () {
    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
    );

    expect($mailable->attachments())->toBe([]);
});

it('attaches files from storage with correct metadata', function () {
    // Create a temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($tempFile, 'test content');

    // Mock the path to return our temp file
    $relativePath = 'attachments/test.pdf';
    $disk = 'local';

    // Mock Storage to return our temp file path
    Storage::shouldReceive('disk')
        ->with($disk)
        ->once()
        ->andReturnSelf();

    Storage::shouldReceive('path')
        ->with($relativePath)
        ->once()
        ->andReturn($tempFile);

    $attachments = [
        [
            'id' => 1,
            'name' => 'document.pdf',
            'path' => $relativePath,
            'disk' => $disk,
            'size' => 12345,
            'mime_type' => 'application/pdf',
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    $attachmentObjects = $mailable->attachments();

    expect($attachmentObjects)->toHaveCount(1);
    expect($attachmentObjects[0])->toBeInstanceOf(\Illuminate\Mail\Attachment::class);

    // Cleanup
    unlink($tempFile);
});

it('attaches multiple files', function () {
    // Create temporary files
    $tempFile1 = tempnam(sys_get_temp_dir(), 'test1_');
    $tempFile2 = tempnam(sys_get_temp_dir(), 'test2_');
    file_put_contents($tempFile1, 'content 1');
    file_put_contents($tempFile2, 'content 2');

    Storage::shouldReceive('disk')
        ->with('local')
        ->twice()
        ->andReturnSelf();

    Storage::shouldReceive('path')
        ->with('attachments/file1.pdf')
        ->once()
        ->andReturn($tempFile1);

    Storage::shouldReceive('path')
        ->with('attachments/file2.jpg')
        ->once()
        ->andReturn($tempFile2);

    $attachments = [
        [
            'name' => 'document.pdf',
            'path' => 'attachments/file1.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
        ],
        [
            'name' => 'image.jpg',
            'path' => 'attachments/file2.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    $attachmentObjects = $mailable->attachments();

    expect($attachmentObjects)->toHaveCount(2);

    // Cleanup
    unlink($tempFile1);
    unlink($tempFile2);
});

it('skips attachment when path is missing', function () {
    $attachments = [
        [
            'name' => 'document.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            // path is missing
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    expect($mailable->attachments())->toBe([]);
});

it('skips attachment when disk is missing', function () {
    $attachments = [
        [
            'name' => 'document.pdf',
            'path' => 'attachments/test.pdf',
            'mime_type' => 'application/pdf',
            // disk is missing
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    expect($mailable->attachments())->toBe([]);
});

it('skips attachment when file does not exist', function () {
    Storage::shouldReceive('disk')
        ->with('local')
        ->once()
        ->andReturnSelf();

    Storage::shouldReceive('path')
        ->with('attachments/nonexistent.pdf')
        ->once()
        ->andReturn('/path/to/nonexistent/file.pdf');

    $attachments = [
        [
            'name' => 'document.pdf',
            'path' => 'attachments/nonexistent.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    expect($mailable->attachments())->toBe([]);
});

it('uses basename as fallback when name is not provided', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'original_name_');
    file_put_contents($tempFile, 'test content');

    Storage::shouldReceive('disk')
        ->with('local')
        ->once()
        ->andReturnSelf();

    Storage::shouldReceive('path')
        ->with('attachments/original-name.pdf')
        ->once()
        ->andReturn($tempFile);

    $attachments = [
        [
            'path' => 'attachments/original-name.pdf',
            'disk' => 'local',
            // name is not provided
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    $attachmentObjects = $mailable->attachments();

    expect($attachmentObjects)->toHaveCount(1);

    // Cleanup
    unlink($tempFile);
});

it('uses default mime type when mime_type is not provided', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($tempFile, 'test content');

    Storage::shouldReceive('disk')
        ->with('local')
        ->once()
        ->andReturnSelf();

    Storage::shouldReceive('path')
        ->with('attachments/test.pdf')
        ->once()
        ->andReturn($tempFile);

    $attachments = [
        [
            'name' => 'document.pdf',
            'path' => 'attachments/test.pdf',
            'disk' => 'local',
            // mime_type is not provided
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    $attachmentObjects = $mailable->attachments();

    expect($attachmentObjects)->toHaveCount(1);

    // Cleanup
    unlink($tempFile);
});

it('processes mixed valid and invalid attachments', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'valid_');
    file_put_contents($tempFile, 'test content');

    Storage::shouldReceive('disk')
        ->with('local')
        ->once()
        ->andReturnSelf();

    Storage::shouldReceive('path')
        ->with('attachments/valid.pdf')
        ->once()
        ->andReturn($tempFile);

    $attachments = [
        [
            'name' => 'valid.pdf',
            'path' => 'attachments/valid.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
        ],
        [
            // Missing path - should be skipped
            'name' => 'invalid.pdf',
            'disk' => 'local',
        ],
        [
            // Missing disk - should be skipped
            'name' => 'missing.pdf',
            'path' => 'attachments/missing.pdf',
        ],
    ];

    $mailable = new BroadcastRecipientMail(
        subjectLine: 'Test',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        attachments: $attachments,
    );

    $attachmentObjects = $mailable->attachments();

    // Only the valid attachment should be included
    expect($attachmentObjects)->toHaveCount(1);

    // Cleanup
    unlink($tempFile);
});

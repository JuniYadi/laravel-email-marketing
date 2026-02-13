<?php

use App\Jobs\SendBroadcastRecipientMail;
use App\Mail\BroadcastRecipientMail;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('passes attachments from broadcast snapshot to mailable', function () {
    $template = EmailTemplate::factory()->create([
        'attachments' => [
            [
                'id' => 1,
                'name' => 'template.pdf',
                'path' => 'attachments/template.pdf',
                'disk' => 'local',
                'size' => 1024,
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    $snapshotAttachments = [
        [
            'id' => 2,
            'name' => 'snapshot.pdf',
            'path' => 'attachments/snapshot.pdf',
            'disk' => 'local',
            'size' => 2048,
            'mime_type' => 'application/pdf',
        ],
    ];

    $broadcast = Broadcast::factory()->create([
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
        'snapshot_subject' => 'Test Subject',
        'snapshot_html_content' => '<p>Test Content</p>',
        'snapshot_builder_schema' => [
            'attachments' => $snapshotAttachments,
        ],
        'from_email' => 'test@example.com',
    ]);

    $contact = Contact::factory()->create();

    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => $contact->email,
        'status' => BroadcastRecipient::STATUS_QUEUED,
    ]);

    $job = new SendBroadcastRecipientMail($recipient->id);
    $job->handle(app(\App\Support\TemplateRenderer::class));

    Mail::assertSent(BroadcastRecipientMail::class, function ($mail) use ($snapshotAttachments) {
        return $mail->attachmentData === $snapshotAttachments;
    });
});

it('falls back to email template attachments when snapshot has no attachments', function () {
    $templateAttachments = [
        [
            'id' => 1,
            'name' => 'template.pdf',
            'path' => 'attachments/template.pdf',
            'disk' => 'local',
            'size' => 1024,
            'mime_type' => 'application/pdf',
        ],
    ];

    $template = EmailTemplate::factory()->create([
        'attachments' => $templateAttachments,
    ]);

    $broadcast = Broadcast::factory()->create([
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
        'snapshot_subject' => 'Test Subject',
        'snapshot_html_content' => '<p>Test Content</p>',
        'snapshot_builder_schema' => [], // No attachments key
        'from_email' => 'test@example.com',
    ]);

    $contact = Contact::factory()->create();

    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => $contact->email,
        'status' => BroadcastRecipient::STATUS_QUEUED,
    ]);

    $job = new SendBroadcastRecipientMail($recipient->id);
    $job->handle(app(\App\Support\TemplateRenderer::class));

    Mail::assertSent(BroadcastRecipientMail::class, function ($mail) use ($templateAttachments) {
        return $mail->attachmentData === $templateAttachments;
    });
});

it('passes empty array when neither snapshot nor template has attachments', function () {
    $template = EmailTemplate::factory()->create([
        'attachments' => [],
    ]);

    $broadcast = Broadcast::factory()->create([
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
        'snapshot_subject' => 'Test Subject',
        'snapshot_html_content' => '<p>Test Content</p>',
        'snapshot_builder_schema' => [],
        'from_email' => 'test@example.com',
    ]);

    $contact = Contact::factory()->create();

    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => $contact->email,
        'status' => BroadcastRecipient::STATUS_QUEUED,
    ]);

    $job = new SendBroadcastRecipientMail($recipient->id);
    $job->handle(app(\App\Support\TemplateRenderer::class));

    Mail::assertSent(BroadcastRecipientMail::class, function ($mail) {
        return $mail->attachmentData === [];
    });
});

it('prefers snapshot attachments over template attachments', function () {
    $templateAttachments = [
        [
            'id' => 1,
            'name' => 'template.pdf',
            'path' => 'attachments/template.pdf',
            'disk' => 'local',
            'size' => 1024,
            'mime_type' => 'application/pdf',
        ],
    ];

    $snapshotAttachments = [
        [
            'id' => 2,
            'name' => 'snapshot.pdf',
            'path' => 'attachments/snapshot.pdf',
            'disk' => 'local',
            'size' => 2048,
            'mime_type' => 'application/pdf',
        ],
    ];

    $template = EmailTemplate::factory()->create([
        'attachments' => $templateAttachments,
    ]);

    $broadcast = Broadcast::factory()->create([
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
        'snapshot_subject' => 'Test Subject',
        'snapshot_html_content' => '<p>Test Content</p>',
        'snapshot_builder_schema' => [
            'attachments' => $snapshotAttachments,
        ],
        'from_email' => 'test@example.com',
    ]);

    $contact = Contact::factory()->create();

    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => $contact->email,
        'status' => BroadcastRecipient::STATUS_QUEUED,
    ]);

    $job = new SendBroadcastRecipientMail($recipient->id);
    $job->handle(app(\App\Support\TemplateRenderer::class));

    Mail::assertSent(BroadcastRecipientMail::class, function ($mail) use ($snapshotAttachments, $templateAttachments) {
        // Should use snapshot attachments, not template attachments
        return $mail->attachmentData === $snapshotAttachments
            && $mail->attachmentData !== $templateAttachments;
    });
});

it('handles multiple attachments from snapshot', function () {
    $snapshotAttachments = [
        [
            'id' => 1,
            'name' => 'first.pdf',
            'path' => 'attachments/first.pdf',
            'disk' => 'local',
            'size' => 1024,
            'mime_type' => 'application/pdf',
        ],
        [
            'id' => 2,
            'name' => 'second.jpg',
            'path' => 'attachments/second.jpg',
            'disk' => 'local',
            'size' => 2048,
            'mime_type' => 'image/jpeg',
        ],
        [
            'id' => 3,
            'name' => 'third.docx',
            'path' => 'attachments/third.docx',
            'disk' => 'local',
            'size' => 4096,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'email_template_id' => $template->id,
        'status' => Broadcast::STATUS_RUNNING,
        'snapshot_subject' => 'Test Subject',
        'snapshot_html_content' => '<p>Test Content</p>',
        'snapshot_builder_schema' => [
            'attachments' => $snapshotAttachments,
        ],
        'from_email' => 'test@example.com',
    ]);

    $contact = Contact::factory()->create();

    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => $contact->email,
        'status' => BroadcastRecipient::STATUS_QUEUED,
    ]);

    $job = new SendBroadcastRecipientMail($recipient->id);
    $job->handle(app(\App\Support\TemplateRenderer::class));

    Mail::assertSent(BroadcastRecipientMail::class, function ($mail) use ($snapshotAttachments) {
        return count($mail->attachmentData) === 3
            && $mail->attachmentData === $snapshotAttachments;
    });
});

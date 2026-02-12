<?php

use App\Jobs\SendBroadcastRecipientMail;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Queue;

it('dispatches queued jobs up to per-minute limit for running broadcasts', function () {
    Queue::fake();

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => 'running',
        'messages_per_minute' => 2,
        'from_email' => 'sender-b1-abcdef@marketing.test.com',
        'snapshot_subject' => 'Hi {{ first_name }}',
        'snapshot_html_content' => '<p>Hello {{ first_name }}</p>',
    ]);

    $validOne = Contact::factory()->create(['is_invalid' => false]);
    $validTwo = Contact::factory()->create(['is_invalid' => false]);
    $validThree = Contact::factory()->create(['is_invalid' => false]);
    $invalid = Contact::factory()->create(['is_invalid' => true]);

    $group->contacts()->attach([$validOne->id, $validTwo->id, $validThree->id, $invalid->id]);

    $this->artisan('broadcasts:dispatch')->assertSuccessful();

    expect($broadcast->recipients()->count())->toBe(3)
        ->and($broadcast->recipients()->where('status', 'queued')->count())->toBe(2)
        ->and($broadcast->recipients()->where('status', 'pending')->count())->toBe(1);

    Queue::assertPushed(SendBroadcastRecipientMail::class, 2);
});

it('promotes due scheduled broadcasts to running and snapshots template before dispatching', function () {
    Queue::fake();

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create([
        'subject' => 'Announcement {{ first_name }}',
        'html_content' => '<h1>Hello {{ first_name }}</h1>',
        'builder_schema' => ['schema_version' => 2, 'rows' => []],
        'version' => 3,
    ]);

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => 'scheduled',
        'starts_at' => now()->subMinute(),
        'messages_per_minute' => 1,
        'from_prefix' => 'juniyadi',
        'from_domain' => 'marketing.test.com',
        'from_email' => null,
        'snapshot_subject' => null,
        'snapshot_html_content' => null,
        'snapshot_builder_schema' => null,
        'snapshot_template_version' => null,
    ]);

    $contact = Contact::factory()->create(['is_invalid' => false]);
    $group->contacts()->attach([$contact->id]);

    $this->artisan('broadcasts:dispatch')->assertSuccessful();

    $broadcast->refresh();

    expect($broadcast->status)->toBe('running')
        ->and($broadcast->started_at)->not->toBeNull()
        ->and($broadcast->snapshot_subject)->toBe('Announcement {{ first_name }}')
        ->and($broadcast->snapshot_html_content)->toBe('<h1>Hello {{ first_name }}</h1>')
        ->and($broadcast->snapshot_template_version)->toBe(3)
        ->and($broadcast->from_email)->toContain('-b'.$broadcast->id.'-')
        ->and($broadcast->from_email)->toEndWith('@marketing.test.com');

    Queue::assertPushed(SendBroadcastRecipientMail::class, 1);
});

it('recovers stale queued recipients and dispatches them again', function () {
    Queue::fake();

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();
    $contact = Contact::factory()->create(['is_invalid' => false]);

    $broadcast = Broadcast::factory()->create([
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
        'status' => 'running',
        'messages_per_minute' => 1,
        'from_email' => 'sender-b1-abcdef@marketing.test.com',
        'snapshot_subject' => 'Hi {{ first_name }}',
        'snapshot_html_content' => '<p>Hello {{ first_name }}</p>',
    ]);

    $group->contacts()->attach([$contact->id]);

    $recipient = $broadcast->recipients()->create([
        'contact_id' => $contact->id,
        'email' => $contact->email,
        'status' => 'queued',
        'queued_at' => now()->subMinutes(10),
    ]);

    $this->artisan('broadcasts:dispatch')->assertSuccessful();

    $recipient->refresh();

    expect($recipient->status)->toBe('queued')
        ->and($recipient->queued_at)->not->toBeNull()
        ->and($recipient->queued_at?->greaterThan(now()->subMinute()))->toBeTrue();

    Queue::assertPushed(SendBroadcastRecipientMail::class, 1);
});

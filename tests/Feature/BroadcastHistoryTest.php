<?php

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\EmailTemplate;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication for broadcast history page', function () {
    $this->get(route('broadcasts.history'))
        ->assertRedirect(route('login'));
});

it('shows filtered recipients and kpis on broadcast history page', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create(['name' => 'VIP Group']);
    $template = EmailTemplate::factory()->create(['name' => 'Spring Template']);

    $broadcast = Broadcast::factory()->create([
        'name' => 'Spring Blast',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $deliveredContact = Contact::factory()->create(['email' => 'delivered@example.com']);
    $bouncedContact = Contact::factory()->create(['email' => 'bounced@example.com']);
    $otherContact = Contact::factory()->create(['email' => 'other@example.com']);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $deliveredContact->id,
        'email' => 'delivered@example.com',
        'status' => BroadcastRecipient::STATUS_DELIVERED,
        'sent_at' => now()->subMinute(),
        'delivered_at' => now()->subMinute(),
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $bouncedContact->id,
        'email' => 'bounced@example.com',
        'status' => BroadcastRecipient::STATUS_BOUNCED,
        'sent_at' => now()->subMinute(),
        'failed_at' => now()->subMinute(),
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $otherContact->id,
        'email' => 'other@example.com',
        'status' => BroadcastRecipient::STATUS_PENDING,
    ]);

    Livewire::test('pages::broadcasts.history')
        ->assertSee('Broadcast History')
        ->set('failedOnly', true)
        ->assertSee('bounced@example.com')
        ->assertDontSee('other@example.com')
        ->set('failedOnly', false)
        ->set('searchEmail', 'delivered@example.com')
        ->assertSee('delivered@example.com')
        ->assertDontSee('bounced@example.com')
        ->set('searchEmail', '')
        ->set('statusFilter', BroadcastRecipient::STATUS_DELIVERED)
        ->assertSee('delivered@example.com')
        ->assertDontSee('bounced@example.com')
        ->set('statusFilter', 'all')
        ->set('failedOnly', false)
        ->assertSee('50.00%');
});

it('applies broadcast filter from url query parameter', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcastOne = Broadcast::factory()->create([
        'name' => 'Broadcast One',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $broadcastTwo = Broadcast::factory()->create([
        'name' => 'Broadcast Two',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $contactOne = Contact::factory()->create(['email' => 'one@example.com']);
    $contactTwo = Contact::factory()->create(['email' => 'two@example.com']);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcastOne->id,
        'contact_id' => $contactOne->id,
        'email' => 'one@example.com',
        'status' => BroadcastRecipient::STATUS_SENT,
        'sent_at' => now(),
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcastTwo->id,
        'contact_id' => $contactTwo->id,
        'email' => 'two@example.com',
        'status' => BroadcastRecipient::STATUS_SENT,
        'sent_at' => now(),
    ]);

    Livewire::withQueryParams(['broadcast_id' => (string) $broadcastOne->id])
        ->test('pages::broadcasts.history')
        ->assertSet('broadcastFilter', (string) $broadcastOne->id)
        ->assertSee('one@example.com')
        ->assertDontSee('two@example.com');
});

it('filters by searchable broadcast picker and supports clearing selection', function () {
    $this->actingAs(User::factory()->create());

    $group = ContactGroup::factory()->create();
    $template = EmailTemplate::factory()->create();

    $broadcastOne = Broadcast::factory()->create([
        'name' => 'Searchable Broadcast One',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $broadcastTwo = Broadcast::factory()->create([
        'name' => 'Searchable Broadcast Two',
        'contact_group_id' => $group->id,
        'email_template_id' => $template->id,
    ]);

    $contactOne = Contact::factory()->create(['email' => 'picker-one@example.com']);
    $contactTwo = Contact::factory()->create(['email' => 'picker-two@example.com']);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcastOne->id,
        'contact_id' => $contactOne->id,
        'email' => 'picker-one@example.com',
        'status' => BroadcastRecipient::STATUS_SENT,
        'sent_at' => now(),
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcastTwo->id,
        'contact_id' => $contactTwo->id,
        'email' => 'picker-two@example.com',
        'status' => BroadcastRecipient::STATUS_SENT,
        'sent_at' => now(),
    ]);

    Livewire::test('pages::broadcasts.history')
        ->set('broadcastSearch', 'Broadcast One')
        ->assertSee('Searchable Broadcast One')
        ->call('selectBroadcast', $broadcastOne->id)
        ->assertSet('broadcastFilter', (string) $broadcastOne->id)
        ->assertSee('picker-one@example.com')
        ->assertDontSee('picker-two@example.com')
        ->call('clearBroadcastFilter')
        ->assertSet('broadcastFilter', '')
        ->assertSet('broadcastSearch', '');
});

it('shows recipient events with payload details in modal', function () {
    $this->actingAs(User::factory()->create());

    $broadcast = Broadcast::factory()->create();
    $contact = Contact::factory()->create(['email' => 'timeline@example.com']);

    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => 'timeline@example.com',
        'status' => BroadcastRecipient::STATUS_BOUNCED,
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_BOUNCE,
        'payload' => [
            'eventType' => 'Bounce',
            'bounce' => ['bounceType' => 'Permanent'],
        ],
        'occurred_at' => now(),
    ]);

    Livewire::test('pages::broadcasts.history')
        ->call('openEventsModal', $recipient->id)
        ->assertSet('showEventsModal', true)
        ->assertSee('timeline@example.com')
        ->assertSee('Bounce')
        ->assertSee('View Payload JSON');
});

it('downloads filtered history as csv', function () {
    $this->actingAs(User::factory()->create());

    $broadcast = Broadcast::factory()->create();
    $contact = Contact::factory()->create(['email' => 'csv@example.com']);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
        'email' => 'csv@example.com',
        'status' => BroadcastRecipient::STATUS_SENT,
        'sent_at' => now(),
    ]);

    Livewire::test('pages::broadcasts.history')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

it('requeues failed-like recipients for selected broadcast', function () {
    $this->actingAs(User::factory()->create());

    $broadcast = Broadcast::factory()->create([
        'status' => Broadcast::STATUS_PAUSED,
        'started_at' => null,
    ]);

    $failedContact = Contact::factory()->create(['email' => 'failed@example.com']);
    $deliveredContact = Contact::factory()->create(['email' => 'delivered@example.com']);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $failedContact->id,
        'email' => 'failed@example.com',
        'status' => BroadcastRecipient::STATUS_FAILED,
        'failed_at' => now()->subMinute(),
        'last_error' => 'Transport failed',
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $deliveredContact->id,
        'email' => 'delivered@example.com',
        'status' => BroadcastRecipient::STATUS_DELIVERED,
        'delivered_at' => now()->subMinute(),
    ]);

    Livewire::test('pages::broadcasts.history')
        ->set('broadcastFilter', (string) $broadcast->id)
        ->call('requeueFailedLikeRecipients')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('broadcast_recipients', [
        'broadcast_id' => $broadcast->id,
        'email' => 'failed@example.com',
        'status' => BroadcastRecipient::STATUS_PENDING,
        'last_error' => null,
    ]);

    $this->assertDatabaseHas('broadcast_recipients', [
        'broadcast_id' => $broadcast->id,
        'email' => 'delivered@example.com',
        'status' => BroadcastRecipient::STATUS_DELIVERED,
    ]);

    $this->assertDatabaseHas('broadcasts', [
        'id' => $broadcast->id,
        'status' => Broadcast::STATUS_RUNNING,
    ]);
});

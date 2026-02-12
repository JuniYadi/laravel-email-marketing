<?php

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use App\Models\Contact;
use App\Models\User;
use Livewire\Livewire;

it('shows correct all-time summary card totals', function () {
    $this->actingAs(User::factory()->create());

    $contacts = Contact::factory()->count(8)->create();
    $broadcast = Broadcast::factory()->create();

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[0]->id,
        'status' => BroadcastRecipient::STATUS_SENT,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[1]->id,
        'status' => BroadcastRecipient::STATUS_DELIVERED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[2]->id,
        'status' => BroadcastRecipient::STATUS_OPENED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[3]->id,
        'status' => BroadcastRecipient::STATUS_CLICKED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[4]->id,
        'status' => BroadcastRecipient::STATUS_BOUNCED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[5]->id,
        'status' => BroadcastRecipient::STATUS_COMPLAINED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[6]->id,
        'status' => BroadcastRecipient::STATUS_FAILED,
    ]);

    BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contacts[7]->id,
        'status' => BroadcastRecipient::STATUS_PENDING,
    ]);

    $summaryCards = Livewire::test('pages::dashboard.index')->get('summaryCards');

    expect($summaryCards)->toBeArray()
        ->and($summaryCards['total_sent'])->toBe(6)
        ->and($summaryCards['total_delivered'])->toBe(3)
        ->and($summaryCards['total_bounced'])->toBe(1)
        ->and($summaryCards['total_reject'])->toBe(1)
        ->and($summaryCards['total_complaint'])->toBe(1)
        ->and($summaryCards['total_contacts'])->toBe(8);
});

it('builds full daily chart series and maps reject to send-failed events', function () {
    $this->actingAs(User::factory()->create());

    $broadcast = Broadcast::factory()->create();
    $contact = Contact::factory()->create();
    $recipient = BroadcastRecipient::factory()->create([
        'broadcast_id' => $broadcast->id,
        'contact_id' => $contact->id,
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_SENT,
        'occurred_at' => now()->subDay(),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_SENT,
        'occurred_at' => now(),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_DELIVERY,
        'occurred_at' => now(),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_BOUNCE,
        'occurred_at' => now()->subDays(2),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_SEND_FAILED,
        'occurred_at' => now()->subDays(3),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_SEND_FAILED,
        'occurred_at' => now()->subDays(4),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_COMPLAINT,
        'occurred_at' => now()->subDays(2),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_OPEN,
        'occurred_at' => now()->subDay(),
    ]);

    BroadcastRecipientEvent::factory()->create([
        'broadcast_id' => $broadcast->id,
        'broadcast_recipient_id' => $recipient->id,
        'event_type' => BroadcastRecipientEvent::TYPE_CLICK,
        'occurred_at' => now(),
    ]);

    $chartPayload = Livewire::test('pages::dashboard.index')->get('chartPayload');

    expect($chartPayload)->toBeArray()
        ->and(isset($chartPayload['labels'], $chartPayload['datasets']))->toBeTrue()
        ->and(count($chartPayload['labels']))->toBe(30)
        ->and(count($chartPayload['datasets']))->toBe(7);

    $datasets = collect($chartPayload['datasets'])->keyBy('key');

    expect(array_sum($datasets['send']['data'] ?? []))->toBe(2)
        ->and(array_sum($datasets['delivered']['data'] ?? []))->toBe(1)
        ->and(array_sum($datasets['bounce']['data'] ?? []))->toBe(1)
        ->and(array_sum($datasets['reject']['data'] ?? []))->toBe(2)
        ->and(array_sum($datasets['complaint']['data'] ?? []))->toBe(1)
        ->and(array_sum($datasets['open']['data'] ?? []))->toBe(1)
        ->and(array_sum($datasets['click']['data'] ?? []))->toBe(1);
});

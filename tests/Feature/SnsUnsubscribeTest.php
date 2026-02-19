<?php

use App\Models\Contact;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('marks contact as unsubscribed on complaint', function () {
    $contact = Contact::factory()->create([
        'email' => 'complainer@example.com',
        'unsubscribed_at' => null,
    ]);

    $payload = [
        'Type' => 'Notification',
        'MessageId' => 'sns-complaint-1',
        'Message' => json_encode([
            'eventType' => 'Complaint',
            'complaint' => [
                'complainedRecipients' => [
                    ['emailAddress' => 'complainer@example.com'],
                ],
            ],
            'mail' => [
                'destination' => ['complainer@example.com'],
                'tags' => [],
            ],
        ]),
    ];

    $this->postJson('/api/webhooks/sns', $payload)
        ->assertOk();

    $contact->refresh();
    expect($contact->unsubscribed_at)->not->toBeNull();
});

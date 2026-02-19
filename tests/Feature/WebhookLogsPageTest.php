<?php

use App\Models\SnsWebhookMessage;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication for webhook logs page', function () {
    $this->get(route('webhooks.logs'))
        ->assertRedirect(route('login'));
});

it('shows webhook logs and supports filtering by sns message type', function () {
    $this->actingAs(User::factory()->create());

    SnsWebhookMessage::factory()->create([
        'message_type' => 'Notification',
        'message_id' => 'sns-notification-1',
        'topic_arn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
        'payload' => [
            'Type' => 'Notification',
            'MessageId' => 'sns-notification-1',
        ],
    ]);

    SnsWebhookMessage::factory()->create([
        'message_type' => 'SubscriptionConfirmation',
        'message_id' => 'sns-subscription-1',
        'token' => str_repeat('b', 512),
        'topic_arn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
        'payload' => [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'sns-subscription-1',
        ],
    ]);

    Livewire::test('pages::webhooks.logs')
        ->assertSee('Webhook Logs')
        ->assertSee('sns-notification-1')
        ->assertSee('sns-subscription-1')
        ->set('messageType', 'SubscriptionConfirmation')
        ->assertSee('sns-subscription-1')
        ->assertDontSee('sns-notification-1')
        ->set('search', 'sns-subscription-1')
        ->assertSee('sns-subscription-1');
});

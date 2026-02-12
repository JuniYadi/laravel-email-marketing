<?php

use App\Models\SnsWebhookMessage;

it('stores detailed notification payload from sns webhooks', function () {
    $payload = [
        'Type' => 'Notification',
        'MessageId' => 'sns-message-1',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
        'Subject' => 'Campaign delivery update',
        'Message' => '{"event":"delivery","campaign":"spring-sale"}',
        'Timestamp' => '2026-02-12T09:45:30.000Z',
        'SignatureVersion' => '1',
        'Signature' => 'sample-signature',
        'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService.pem',
        'UnsubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=Unsubscribe',
    ];

    $response = $this
        ->withHeaders([
            'x-amz-sns-message-type' => 'Notification',
            'x-amz-sns-topic-arn' => $payload['TopicArn'],
            'x-amz-sns-message-id' => $payload['MessageId'],
        ])
        ->postJson('/webhooks/sns', $payload);

    $response
        ->assertSuccessful()
        ->assertJson([
            'status' => 'received',
            'type' => 'Notification',
        ]);

    $this->assertDatabaseHas('sns_webhook_messages', [
        'message_type' => 'Notification',
        'message_id' => 'sns-message-1',
        'topic_arn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
        'subject' => 'Campaign delivery update',
        'signature_version' => '1',
    ]);

    $webhook = SnsWebhookMessage::query()->first();

    expect($webhook)->not->toBeNull()
        ->and($webhook?->payload['Message'])->toBe('{"event":"delivery","campaign":"spring-sale"}')
        ->and($webhook?->headers)->toHaveKey('x-amz-sns-message-type')
        ->and($webhook?->headers['x-amz-sns-message-type'][0])->toBe('Notification');
});

it('stores subscription confirmation metadata for later processing', function () {
    $payload = [
        'Type' => 'SubscriptionConfirmation',
        'MessageId' => 'sns-message-2',
        'Token' => 'subscription-token',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
        'Message' => 'You have chosen to subscribe to the topic.',
        'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription',
        'Timestamp' => '2026-02-12T09:46:00.000Z',
        'SignatureVersion' => '1',
        'Signature' => 'sample-signature',
        'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService.pem',
    ];

    $response = $this->postJson('/webhooks/sns', $payload);

    $response
        ->assertSuccessful()
        ->assertJson([
            'status' => 'received',
            'type' => 'SubscriptionConfirmation',
        ]);

    $this->assertDatabaseHas('sns_webhook_messages', [
        'message_type' => 'SubscriptionConfirmation',
        'message_id' => 'sns-message-2',
        'topic_arn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
        'token' => 'subscription-token',
        'subscribe_url' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription',
    ]);
});

<?php

namespace Database\Factories;

use App\Models\SnsWebhookMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SnsWebhookMessage>
 */
class SnsWebhookMessageFactory extends Factory
{
    protected $model = SnsWebhookMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_type' => 'Notification',
            'message_id' => (string) fake()->uuid(),
            'topic_arn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
            'subject' => fake()->sentence(3),
            'message' => json_encode(['event' => 'delivery', 'status' => 'ok'], JSON_THROW_ON_ERROR),
            'token' => null,
            'subscribe_url' => null,
            'unsubscribe_url' => 'https://sns.us-east-1.amazonaws.com/?Action=Unsubscribe',
            'signature_version' => '1',
            'signature' => fake()->sha256(),
            'signing_cert_url' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService.pem',
            'sns_timestamp' => fake()->dateTimeBetween('-1 day', 'now'),
            'payload' => [
                'Type' => 'Notification',
                'MessageId' => (string) fake()->uuid(),
                'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:marketing-events',
                'Message' => '{"event":"delivery"}',
            ],
            'headers' => [
                'x-amz-sns-message-type' => ['Notification'],
            ],
            'raw_body' => '{"Type":"Notification"}',
        ];
    }
}

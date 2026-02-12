<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSnsWebhookRequest;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use App\Models\SnsWebhookMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SnsWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreSnsWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $validated;
        }

        $webhook = SnsWebhookMessage::query()->create([
            'message_type' => $validated['Type'],
            'message_id' => $validated['MessageId'],
            'topic_arn' => $validated['TopicArn'] ?? null,
            'subject' => $validated['Subject'] ?? null,
            'message' => $validated['Message'] ?? null,
            'token' => $validated['Token'] ?? null,
            'subscribe_url' => $validated['SubscribeURL'] ?? null,
            'unsubscribe_url' => $validated['UnsubscribeURL'] ?? null,
            'signature_version' => $validated['SignatureVersion'] ?? null,
            'signature' => $validated['Signature'] ?? null,
            'signing_cert_url' => $validated['SigningCertURL'] ?? null,
            'sns_timestamp' => $validated['Timestamp'] ?? null,
            'payload' => $payload,
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
        ]);

        $this->trackBroadcastRecipientEvent(
            messageType: (string) $validated['Type'],
            message: $validated['Message'] ?? null,
        );

        return response()->json([
            'status' => 'received',
            'type' => $webhook->message_type,
            'message_id' => $webhook->message_id,
        ]);
    }

    /**
     * Track SNS notification events against broadcast recipients.
     */
    protected function trackBroadcastRecipientEvent(string $messageType, ?string $message): void
    {
        if ($messageType !== 'Notification' || $message === null) {
            return;
        }

        $decoded = json_decode($message, true);

        if (! is_array($decoded)) {
            return;
        }

        $eventType = Str::lower((string) ($decoded['eventType'] ?? ''));
        $mappedEventType = $this->mapSnsEventType($eventType);

        if ($mappedEventType === null) {
            return;
        }

        $mail = (array) ($decoded['mail'] ?? []);
        $messageId = $mail['messageId'] ?? null;
        $tags = (array) ($mail['tags'] ?? []);

        $taggedBroadcastId = $this->extractTagValue($tags, 'broadcast_id');
        $taggedRecipientId = $this->extractTagValue($tags, 'broadcast_recipient_id');
        $broadcastId = $taggedBroadcastId !== null ? (int) $taggedBroadcastId : null;
        $recipientId = $taggedRecipientId !== null ? (int) $taggedRecipientId : null;

        $recipient = $this->resolveRecipient(
            recipientId: $recipientId,
            broadcastId: $broadcastId,
            providerMessageId: is_string($messageId) ? $messageId : null,
        );

        if ($recipient !== null) {
            $this->applyRecipientStatus(
                recipient: $recipient,
                eventType: $eventType,
                providerMessageId: is_string($messageId) ? $messageId : null,
                occurredAt: $this->resolveOccurredAt($decoded),
            );

            $broadcastId = $recipient->broadcast_id;
        }

        if ($broadcastId === null || ! Broadcast::query()->whereKey($broadcastId)->exists()) {
            return;
        }

        BroadcastRecipientEvent::query()->create([
            'broadcast_id' => $broadcastId,
            'broadcast_recipient_id' => $recipient?->id,
            'provider_message_id' => is_string($messageId) ? $messageId : null,
            'event_type' => $mappedEventType,
            'payload' => $decoded,
            'occurred_at' => $this->resolveOccurredAt($decoded),
        ]);
    }

    /**
     * Resolve a broadcast recipient for SNS event tracking.
     */
    protected function resolveRecipient(?int $recipientId, ?int $broadcastId, ?string $providerMessageId): ?BroadcastRecipient
    {
        if ($recipientId !== null) {
            return BroadcastRecipient::query()->find($recipientId);
        }

        if ($broadcastId === null || $providerMessageId === null) {
            return null;
        }

        return BroadcastRecipient::query()
            ->where('broadcast_id', $broadcastId)
            ->where('provider_message_id', $providerMessageId)
            ->first();
    }

    /**
     * Apply the latest recipient status and timestamps from an SNS event.
     */
    protected function applyRecipientStatus(BroadcastRecipient $recipient, string $eventType, ?string $providerMessageId, Carbon $occurredAt): void
    {
        if ($providerMessageId !== null) {
            $recipient->provider_message_id = $providerMessageId;
        }

        if ($eventType === 'delivery') {
            $recipient->status = BroadcastRecipient::STATUS_DELIVERED;
            $recipient->delivered_at = $occurredAt;
        }

        if ($eventType === 'open') {
            $recipient->status = BroadcastRecipient::STATUS_OPENED;
            $recipient->opened_at = $occurredAt;
        }

        if ($eventType === 'click') {
            $recipient->status = BroadcastRecipient::STATUS_CLICKED;
            $recipient->clicked_at = $occurredAt;
        }

        if ($eventType === 'bounce') {
            $recipient->status = BroadcastRecipient::STATUS_BOUNCED;
            $recipient->failed_at = $occurredAt;
        }

        if ($eventType === 'complaint') {
            $recipient->status = BroadcastRecipient::STATUS_COMPLAINED;
            $recipient->failed_at = $occurredAt;
        }

        $recipient->save();
    }

    /**
     * Extract a single tag value from SNS mail tags.
     *
     * AWS SES returns tags in format: [['name' => 'key', 'value' => 'val'], ...]
     * We need to find the tag by name and return its value.
     *
     * @param  array<string, mixed>  $tags
     */
    protected function extractTagValue(array $tags, string $key): ?string
    {
        foreach ($tags as $tag) {
            // Handle AWS SES tag format: ['name' => 'key', 'value' => 'val']
            if (is_array($tag) && isset($tag['name']) && $tag['name'] === $key) {
                return isset($tag['value']) ? (string) $tag['value'] : null;
            }

            // Handle flat array format: ['key' => 'value'] (legacy support)
            if (isset($tags[$key])) {
                $value = $tags[$key];
                if (is_array($value) && isset($value[0])) {
                    return (string) $value[0];
                }
                if (is_scalar($value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    /**
     * Resolve event timestamp from SNS payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveOccurredAt(array $payload): Carbon
    {
        $timestamp = data_get($payload, 'delivery.timestamp')
            ?? data_get($payload, 'bounce.timestamp')
            ?? data_get($payload, 'complaint.timestamp')
            ?? data_get($payload, 'open.timestamp')
            ?? data_get($payload, 'click.timestamp')
            ?? data_get($payload, 'mail.timestamp');

        if (is_string($timestamp)) {
            return Carbon::parse($timestamp);
        }

        return now();
    }

    /**
     * Convert SNS event type to internal event type.
     */
    protected function mapSnsEventType(string $eventType): ?string
    {
        return match ($eventType) {
            'delivery' => BroadcastRecipientEvent::TYPE_DELIVERY,
            'bounce' => BroadcastRecipientEvent::TYPE_BOUNCE,
            'complaint' => BroadcastRecipientEvent::TYPE_COMPLAINT,
            'open' => BroadcastRecipientEvent::TYPE_OPEN,
            'click' => BroadcastRecipientEvent::TYPE_CLICK,
            default => null,
        };
    }
}

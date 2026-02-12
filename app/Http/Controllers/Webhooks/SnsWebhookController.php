<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSnsWebhookRequest;
use App\Models\SnsWebhookMessage;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'status' => 'received',
            'type' => $webhook->message_type,
            'message_id' => $webhook->message_id,
        ]);
    }
}

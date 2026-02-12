<?php

namespace App\Jobs;

use App\Mail\BroadcastRecipientMail;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use App\Support\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBroadcastRecipientMail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $recipientId) {}

    /**
     * Execute the job.
     */
    public function handle(TemplateRenderer $renderer): void
    {
        $recipient = BroadcastRecipient::query()
            ->with(['broadcast', 'contact'])
            ->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        if (! in_array($recipient->status, [BroadcastRecipient::STATUS_PENDING, BroadcastRecipient::STATUS_QUEUED], true)) {
            return;
        }

        $broadcast = $recipient->broadcast;

        if ($broadcast === null) {
            return;
        }

        if ($broadcast->status === Broadcast::STATUS_PAUSED) {
            $recipient->status = BroadcastRecipient::STATUS_PENDING;
            $recipient->save();

            return;
        }

        if ($broadcast->status === Broadcast::STATUS_CANCELLED) {
            $recipient->status = BroadcastRecipient::STATUS_SKIPPED;
            $recipient->skipped_at = now();
            $recipient->save();

            return;
        }

        $contact = $recipient->contact;

        $variables = [
            'first_name' => $contact?->first_name,
            'last_name' => $contact?->last_name,
            'full_name' => $contact?->full_name,
            'email' => $recipient->email,
            'company' => $contact?->company,
        ];

        $subject = $renderer->render((string) $broadcast->snapshot_subject, $variables);
        $htmlContent = $renderer->render((string) $broadcast->snapshot_html_content, $variables);

        try {
            $sentMessage = Mail::to($recipient->email)->send(new BroadcastRecipientMail(
                subjectLine: $subject,
                htmlContent: $htmlContent,
                fromName: $broadcast->from_name,
                fromEmail: (string) $broadcast->from_email,
                replyToAddress: $broadcast->reply_to,
                messageMetadata: [
                    'broadcast_id' => (string) $broadcast->id,
                    'broadcast_recipient_id' => (string) $recipient->id,
                    'contact_id' => (string) $recipient->contact_id,
                ],
            ));

            $providerMessageId = null;

            if ($sentMessage !== null) {
                $symfonySentMessage = $sentMessage->getSymfonySentMessage();

                if (method_exists($symfonySentMessage, 'getMessageId')) {
                    $providerMessageId = $symfonySentMessage->getMessageId();
                }
            }

            $recipient->status = BroadcastRecipient::STATUS_SENT;
            $recipient->provider_message_id = $providerMessageId;
            $recipient->attempt_count++;
            $recipient->sent_at = now();
            $recipient->failed_at = null;
            $recipient->last_error = null;
            $recipient->save();

            $recipient->events()->create([
                'broadcast_id' => $broadcast->id,
                'provider_message_id' => $providerMessageId,
                'event_type' => BroadcastRecipientEvent::TYPE_SENT,
                'payload' => ['status' => BroadcastRecipient::STATUS_SENT],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $recipient->status = BroadcastRecipient::STATUS_FAILED;
            $recipient->attempt_count++;
            $recipient->failed_at = now();
            $recipient->last_error = $exception->getMessage();
            $recipient->save();

            $recipient->events()->create([
                'broadcast_id' => $broadcast->id,
                'provider_message_id' => null,
                'event_type' => BroadcastRecipientEvent::TYPE_SEND_FAILED,
                'payload' => [
                    'status' => BroadcastRecipient::STATUS_FAILED,
                    'message' => $exception->getMessage(),
                ],
                'occurred_at' => now(),
            ]);
        }
    }

    /**
     * Handle failed job execution.
     */
    public function failed(Throwable $exception): void
    {
        $recipient = BroadcastRecipient::query()->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        if (in_array($recipient->status, [BroadcastRecipient::STATUS_SENT, BroadcastRecipient::STATUS_DELIVERED, BroadcastRecipient::STATUS_OPENED, BroadcastRecipient::STATUS_CLICKED], true)) {
            return;
        }

        $recipient->status = BroadcastRecipient::STATUS_FAILED;
        $recipient->attempt_count = max(1, $recipient->attempt_count);
        $recipient->failed_at = now();
        $recipient->last_error = $exception->getMessage();
        $recipient->save();

        $broadcast = $recipient->broadcast;

        if ($broadcast === null) {
            return;
        }

        $recipient->events()->create([
            'broadcast_id' => $broadcast->id,
            'provider_message_id' => $recipient->provider_message_id,
            'event_type' => BroadcastRecipientEvent::TYPE_SEND_FAILED,
            'payload' => [
                'status' => BroadcastRecipient::STATUS_FAILED,
                'message' => $exception->getMessage(),
            ],
            'occurred_at' => now(),
        ]);
    }
}

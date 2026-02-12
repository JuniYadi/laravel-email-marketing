<?php

namespace App\Console\Commands;

use App\Jobs\SendBroadcastRecipientMail;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastRecipientEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchBroadcastsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcasts:dispatch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending broadcast recipients based on per-minute limits.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->promoteDueScheduledBroadcasts();

        Broadcast::query()
            ->where('status', Broadcast::STATUS_RUNNING)
            ->with('group:id', 'template:id,subject,html_content,builder_schema,version')
            ->orderBy('id')
            ->each(function (Broadcast $broadcast): void {
                $this->ensureSnapshotAndSender($broadcast);
                $this->ensureRecipientsForGroup($broadcast);
                $this->recoverStaleQueuedRecipients($broadcast);
                $this->queuePendingRecipients($broadcast);
                $this->markCompletedWhenFinished($broadcast);
            });

        return self::SUCCESS;
    }

    /**
     * Promote due scheduled broadcasts to running.
     */
    protected function promoteDueScheduledBroadcasts(): void
    {
        Broadcast::query()
            ->where('status', Broadcast::STATUS_SCHEDULED)
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->orderBy('id')
            ->each(function (Broadcast $broadcast): void {
                $broadcast->status = Broadcast::STATUS_RUNNING;

                if ($broadcast->started_at === null) {
                    $broadcast->started_at = now();
                }

                $broadcast->save();
            });
    }

    /**
     * Ensure template snapshot and sender address exist for a running broadcast.
     */
    protected function ensureSnapshotAndSender(Broadcast $broadcast): void
    {
        $template = $broadcast->template;

        if (
            $broadcast->snapshot_subject === null
            || $broadcast->snapshot_html_content === null
            || $broadcast->snapshot_template_version === null
        ) {
            $broadcast->snapshot_subject = $template?->subject ?? '';
            $broadcast->snapshot_html_content = $template?->html_content ?? '';
            $broadcast->snapshot_builder_schema = $template?->builder_schema;
            $broadcast->snapshot_template_version = $template?->version ?? 1;
        }

        if ($broadcast->from_email === null) {
            $broadcast->from_email = $this->generateFromEmail($broadcast);
        }

        if ($broadcast->started_at === null) {
            $broadcast->started_at = now();
        }

        $broadcast->save();
    }

    /**
     * Generate recipients for valid, subscribed contacts in the broadcast group.
     */
    protected function ensureRecipientsForGroup(Broadcast $broadcast): void
    {
        $contacts = $broadcast->group
            ->contacts()
            ->subscribed()
            ->where('contacts.is_invalid', false)
            ->select('contacts.id', 'contacts.email')
            ->get();

        $contacts->each(function (object $contact) use ($broadcast): void {
            $recipient = BroadcastRecipient::query()->firstOrCreate(
                [
                    'broadcast_id' => $broadcast->id,
                    'contact_id' => $contact->id,
                ],
                [
                    'email' => $contact->email,
                    'status' => BroadcastRecipient::STATUS_PENDING,
                ],
            );

            if ($recipient->email !== $contact->email) {
                $recipient->email = $contact->email;
                $recipient->save();
            }
        });
    }

    /**
     * Queue pending recipients up to this minute's broadcast limit.
     */
    protected function queuePendingRecipients(Broadcast $broadcast): void
    {
        $recipientIds = [];

        DB::transaction(function () use ($broadcast, &$recipientIds): void {
            $recipientIds = BroadcastRecipient::query()
                ->where('broadcast_id', $broadcast->id)
                ->where('status', BroadcastRecipient::STATUS_PENDING)
                ->orderBy('id')
                ->limit($broadcast->messages_per_minute)
                ->pluck('id')
                ->all();

            if ($recipientIds === []) {
                return;
            }

            BroadcastRecipient::query()
                ->whereIn('id', $recipientIds)
                ->where('status', BroadcastRecipient::STATUS_PENDING)
                ->update([
                    'status' => BroadcastRecipient::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);
        });

        if ($recipientIds === []) {
            return;
        }

        $recipients = BroadcastRecipient::query()
            ->whereIn('id', $recipientIds)
            ->get();

        foreach ($recipients as $recipient) {
            SendBroadcastRecipientMail::dispatch($recipient->id);
        }

        $events = $recipients->map(function (BroadcastRecipient $recipient): array {
            return [
                'broadcast_id' => $recipient->broadcast_id,
                'broadcast_recipient_id' => $recipient->id,
                'provider_message_id' => null,
                'event_type' => BroadcastRecipientEvent::TYPE_QUEUED,
                'payload' => json_encode(['status' => BroadcastRecipient::STATUS_QUEUED], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        BroadcastRecipientEvent::query()->insert($events);
    }

    /**
     * Recover stale queued recipients so they can be queued again.
     */
    protected function recoverStaleQueuedRecipients(Broadcast $broadcast): void
    {
        BroadcastRecipient::query()
            ->where('broadcast_id', $broadcast->id)
            ->where('status', BroadcastRecipient::STATUS_QUEUED)
            ->whereNull('sent_at')
            ->where('queued_at', '<=', now()->subMinutes(5))
            ->update([
                'status' => BroadcastRecipient::STATUS_PENDING,
                'queued_at' => null,
            ]);
    }

    /**
     * Mark broadcasts as completed when no pending/queued recipients remain.
     */
    protected function markCompletedWhenFinished(Broadcast $broadcast): void
    {
        $hasPendingOrQueued = $broadcast->recipients()
            ->whereIn('status', [BroadcastRecipient::STATUS_PENDING, BroadcastRecipient::STATUS_QUEUED])
            ->exists();

        if ($hasPendingOrQueued) {
            return;
        }

        if (! $broadcast->recipients()->exists()) {
            return;
        }

        $broadcast->status = Broadcast::STATUS_COMPLETED;
        $broadcast->completed_at = now();
        $broadcast->save();
    }

    /**
     * Generate sender email for a broadcast.
     */
    protected function generateFromEmail(Broadcast $broadcast): string
    {
        $prefix = Str::of($broadcast->from_prefix)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        $random = Str::lower(Str::random(6));

        return sprintf('%s-b%d-%s@%s', $prefix, $broadcast->id, $random, $broadcast->from_domain);
    }
}

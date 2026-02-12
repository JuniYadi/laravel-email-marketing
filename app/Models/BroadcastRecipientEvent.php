<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastRecipientEvent extends Model
{
    /** @use HasFactory<\Database\Factories\BroadcastRecipientEventFactory> */
    use HasFactory;

    public const TYPE_QUEUED = 'queued';

    public const TYPE_SENT = 'sent';

    public const TYPE_SEND_FAILED = 'send_failed';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_BOUNCE = 'bounce';

    public const TYPE_COMPLAINT = 'complaint';

    public const TYPE_OPEN = 'open';

    public const TYPE_CLICK = 'click';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'broadcast_id',
        'broadcast_recipient_id',
        'provider_message_id',
        'event_type',
        'payload',
        'occurred_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * The broadcast this event belongs to.
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * The recipient this event belongs to.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(BroadcastRecipient::class, 'broadcast_recipient_id');
    }
}

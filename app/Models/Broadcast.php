<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    /** @use HasFactory<\Database\Factories\BroadcastFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_group_id',
        'email_template_id',
        'status',
        'starts_at',
        'starts_at_timezone',
        'messages_per_minute',
        'reply_to',
        'from_name',
        'from_prefix',
        'from_domain',
        'from_email',
        'snapshot_subject',
        'snapshot_html_content',
        'snapshot_builder_schema',
        'snapshot_template_version',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'messages_per_minute' => 'integer',
            'snapshot_builder_schema' => 'array',
            'snapshot_template_version' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * The contact group targeted by this broadcast.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id');
    }

    /**
     * The email template selected for this broadcast.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * Recipients queued or sent by this broadcast.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    /**
     * Tracking events attached to this broadcast.
     */
    public function recipientEvents(): HasMany
    {
        return $this->hasMany(BroadcastRecipientEvent::class);
    }
}

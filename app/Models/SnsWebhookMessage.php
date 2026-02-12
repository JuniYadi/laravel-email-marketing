<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SnsWebhookMessage extends Model
{
    /** @use HasFactory<\Database\Factories\SnsWebhookMessageFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'message_type',
        'message_id',
        'topic_arn',
        'subject',
        'message',
        'token',
        'subscribe_url',
        'unsubscribe_url',
        'signature_version',
        'signature',
        'signing_cert_url',
        'sns_timestamp',
        'payload',
        'headers',
        'raw_body',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sns_timestamp' => 'immutable_datetime',
            'payload' => 'array',
            'headers' => 'array',
        ];
    }
}

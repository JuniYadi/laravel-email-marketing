<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'company',
        'is_invalid',
        'unsubscribed_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['full_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_invalid' => 'boolean',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * Build the contact full name from first and last name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->first_name.' '.$this->last_name),
        );
    }

    /**
     * The groups the contact belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_contact_group');
    }

    /**
     * Broadcast recipient rows for this contact.
     */
    public function broadcastRecipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }
}

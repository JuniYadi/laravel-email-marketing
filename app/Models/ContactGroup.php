<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ContactGroupFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The contacts assigned to this group.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_contact_group');
    }

    /**
     * Broadcasts configured for this group.
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }
}

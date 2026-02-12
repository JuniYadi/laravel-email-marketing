<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailTemplateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subject',
        'html_content',
        'builder_schema',
        'is_active',
        'version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'builder_schema' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * Broadcasts that reference this template.
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }
}

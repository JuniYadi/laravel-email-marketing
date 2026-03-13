<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPage extends Model
{
    /** @use HasFactory<\Database\Factories\LandingPageFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'landing_page_template_id',
        'title',
        'slug',
        'custom_domain',
        'status',
        'meta',
        'form_data',
        'template_snapshot',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'form_data' => 'array',
            'template_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LandingPageTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LandingPageTemplate::class, 'landing_page_template_id');
    }

    /**
     * @return HasMany<LandingPageView, $this>
     */
    public function viewEvents(): HasMany
    {
        return $this->hasMany(LandingPageView::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}

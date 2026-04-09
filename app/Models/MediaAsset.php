<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    /** @use HasFactory<\Database\Factories\MediaAssetFactory> */
    use HasFactory;

    use SoftDeletes;

    public const KIND_IMAGE = 'image';

    public const KIND_PDF = 'pdf';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'user_id',
        'original_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'public_url',
        'kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            if (! filled($asset->external_id)) {
                $asset->external_id = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->withoutTrashed();
    }

    public function scopeTrashed($query)
    {
        return $query->onlyTrashed();
    }

    public function scopeKind($query, ?string $kind)
    {
        if (! filled($kind)) {
            return $query;
        }

        return $query->where('kind', $kind);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! filled($search)) {
            return $query;
        }

        return $query->where(function ($searchQuery) use ($search): void {
            $searchQuery
                ->where('original_name', 'like', '%'.$search.'%')
                ->orWhere('external_id', 'like', '%'.$search.'%');
        });
    }
}

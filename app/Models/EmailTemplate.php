<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailTemplateFactory> */
    use HasFactory;

    public const ALLOWED_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/msword', // doc
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel', // xls
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // pptx
        'application/vnd.ms-powerpoint', // ppt
    ];

    public const ALLOWED_ATTACHMENT_EXTENSIONS = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'];

    public const MAX_TOTAL_ATTACHMENT_SIZE_MB = 40;

    public const MAX_TOTAL_ATTACHMENT_SIZE_BYTES = self::MAX_TOTAL_ATTACHMENT_SIZE_MB * 1024 * 1024;

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
        'attachments',
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
            'attachments' => 'array',
        ];
    }

    /**
     * Broadcasts that reference this template.
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }

    /**
     * Get the total size of all attachments in bytes.
     */
    public function getTotalAttachmentSize(): int
    {
        if (empty($this->attachments)) {
            return 0;
        }

        return collect($this->attachments)->sum('size');
    }

    /**
     * Check if the template has any attachments.
     */
    public function hasAttachments(): bool
    {
        return ! empty($this->attachments);
    }
}

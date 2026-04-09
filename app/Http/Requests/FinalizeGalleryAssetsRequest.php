<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeGalleryAssetsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_DISKS = ['s3'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uploads' => ['required', 'array', 'min:1'],
            'uploads.*.name' => ['required', 'string', 'max:255'],
            'uploads.*.path' => ['required', 'string'],
            'uploads.*.size' => ['required', 'integer', 'min:1', 'max:'.PresignGalleryAssetsRequest::MAX_FILE_SIZE_BYTES],
            'uploads.*.mime_type' => ['required', 'string', Rule::in(PresignGalleryAssetsRequest::ALLOWED_MIME_TYPES)],
            'uploads.*.disk' => ['required', 'string', Rule::in(self::ALLOWED_DISKS)],
        ];
    }
}

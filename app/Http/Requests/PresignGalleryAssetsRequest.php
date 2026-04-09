<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresignGalleryAssetsRequest extends FormRequest
{
    public const MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024;

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'application/pdf',
    ];

    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf'];

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
            'files' => ['required', 'array', 'min:1'],
            'files.*.name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $extension = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));

                if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    $fail('One or more files have an invalid extension. Allowed: '.implode(', ', self::ALLOWED_EXTENSIONS));
                }
            }],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_FILE_SIZE_BYTES],
            'files.*.mime_type' => ['required', 'string', Rule::in(self::ALLOWED_MIME_TYPES)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeTemplateAttachmentsRequest extends FormRequest
{
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
            'uploads.*.name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $extension = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));

                if (! in_array($extension, EmailTemplate::ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                    $fail('One or more files have an invalid extension. Allowed: '.implode(', ', EmailTemplate::ALLOWED_ATTACHMENT_EXTENSIONS));
                }
            }],
            'uploads.*.path' => ['required', 'string'],
            'uploads.*.size' => ['required', 'integer', 'min:1', 'max:'.EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES],
            'uploads.*.mime_type' => ['required', 'string', Rule::in(EmailTemplate::ALLOWED_ATTACHMENT_MIME_TYPES)],
            'uploads.*.disk' => ['required', 'string', Rule::in(array_keys((array) config('filesystems.disks', [])))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uploads.required' => 'No uploaded files were provided.',
            'uploads.*.mime_type.in' => 'One or more files are not a valid type. Only PDF, Word, Excel, and PowerPoint files are allowed.',
            'uploads.*.size.max' => 'One or more files exceed the 40MB maximum file size.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresignTemplateAttachmentsRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1'],
            'files.*.name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $extension = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));

                if (! in_array($extension, EmailTemplate::ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                    $fail('One or more files have an invalid extension. Allowed: '.implode(', ', EmailTemplate::ALLOWED_ATTACHMENT_EXTENSIONS));
                }
            }],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.EmailTemplate::MAX_TOTAL_ATTACHMENT_SIZE_BYTES],
            'files.*.mime_type' => ['required', 'string', Rule::in(EmailTemplate::ALLOWED_ATTACHMENT_MIME_TYPES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one file.',
            'files.array' => 'Invalid files payload.',
            'files.*.size.max' => 'One or more files exceed the 40MB maximum file size.',
            'files.*.mime_type.in' => 'One or more files are not a valid type. Only PDF, Word, Excel, and PowerPoint files are allowed.',
        ];
    }
}

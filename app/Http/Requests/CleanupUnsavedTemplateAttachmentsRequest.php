<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CleanupUnsavedTemplateAttachmentsRequest extends FormRequest
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
            'attachments' => ['required', 'array'],
            'attachments.*.path' => ['required', 'string'],
            'attachments.*.disk' => ['required', 'string', Rule::in(array_keys((array) config('filesystems.disks', [])))],
        ];
    }
}

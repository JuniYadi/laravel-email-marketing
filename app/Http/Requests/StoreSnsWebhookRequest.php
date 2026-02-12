<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSnsWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Type' => ['required', 'string', 'max:120'],
            'MessageId' => ['required', 'string', 'max:255'],
            'TopicArn' => ['nullable', 'string', 'max:2048'],
            'Subject' => ['nullable', 'string', 'max:255'],
            'Message' => ['nullable', 'string'],
            'Token' => ['nullable', 'string', 'max:2048'],
            'SubscribeURL' => ['nullable', 'url', 'max:2048'],
            'UnsubscribeURL' => ['nullable', 'url', 'max:2048'],
            'SignatureVersion' => ['nullable', 'string', 'max:30'],
            'Signature' => ['nullable', 'string'],
            'SigningCertURL' => ['nullable', 'url', 'max:2048'],
            'Timestamp' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Type.required' => 'SNS webhook payload must include a Type field.',
            'MessageId.required' => 'SNS webhook payload must include a MessageId field.',
            'Timestamp.date' => 'SNS webhook Timestamp must be a valid date value.',
        ];
    }
}

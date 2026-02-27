<?php

namespace App\Http\Requests;

use App\Models\SnsWebhookMessage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Throwable;

class StoreSnsWebhookRequest extends FormRequest
{
    /**
     * Prepare SNS raw payloads for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('Type') !== null) {
            return;
        }

        $rawBody = $this->getContent();

        if (! is_string($rawBody) || trim($rawBody) === '') {
            return;
        }

        $decodedPayload = json_decode($rawBody, true);

        if (! is_array($decodedPayload)) {
            return;
        }

        $this->merge($decodedPayload);
    }

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

    /**
     * Ensure webhook validation failures return JSON instead of redirects.
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->storeFailedWebhookAttempt();

        throw new HttpResponseException(
            response()->json([
                'status' => 'failed',
                'message' => 'Invalid SNS webhook payload.',
                'errors' => $validator->errors(),
            ], 422),
        );
    }

    protected function storeFailedWebhookAttempt(): void
    {
        $rawBody = $this->getContent();
        $decodedPayload = json_decode($rawBody, true);

        if (! is_array($decodedPayload)) {
            $decodedPayload = [
                '_raw_body' => is_string($rawBody) ? $rawBody : '',
            ];
        }

        $messageType = $this->input('Type')
            ?? $this->header('x-amz-sns-message-type')
            ?? 'Unknown';

        $messageId = $this->input('MessageId')
            ?? $this->header('x-amz-sns-message-id');

        $topicArn = $this->input('TopicArn')
            ?? $this->header('x-amz-sns-topic-arn');

        try {
            SnsWebhookMessage::query()->create([
                'message_type' => Str::limit((string) $messageType, 120, ''),
                'message_id' => $messageId !== null ? Str::limit((string) $messageId, 255, '') : null,
                'topic_arn' => $topicArn !== null ? Str::limit((string) $topicArn, 255, '') : null,
                'subject' => $this->input('Subject'),
                'message' => $this->input('Message'),
                'token' => $this->input('Token'),
                'subscribe_url' => $this->input('SubscribeURL'),
                'unsubscribe_url' => $this->input('UnsubscribeURL'),
                'signature_version' => $this->input('SignatureVersion'),
                'signature' => $this->input('Signature'),
                'signing_cert_url' => $this->input('SigningCertURL'),
                'sns_timestamp' => $this->input('Timestamp'),
                'payload' => $decodedPayload,
                'headers' => $this->headers->all(),
                'raw_body' => is_string($rawBody) ? $rawBody : '',
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BroadcastRecipientMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $subjectLine,
        public string $htmlContent,
        public string $fromName,
        public string $fromEmail,
        public string $replyToAddress,
        /** @var array<string, string> */
        public array $messageMetadata = [],
        public string $unsubscribeUrl = '',
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            replyTo: [new Address($this->replyToAddress)],
            subject: $this->subjectLine,
            tags: ['broadcast'],
            metadata: $this->messageMetadata,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlContent,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get the message headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if ($this->unsubscribeUrl === '') {
            return [];
        }

        return [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }
}

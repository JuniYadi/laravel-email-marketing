<?php

use App\Mail\BroadcastRecipientMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

it('sends with log mailer without header type errors', function () {
    Config::set('mail.default', 'log');
    Config::set('mail.mailers.log.channel', 'single');

    $mail = new BroadcastRecipientMail(
        subjectLine: 'Test Subject',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        messageMetadata: [],
        unsubscribeUrl: 'https://example.com/unsubscribe/1?signature=abc123',
    );

    Mail::to('recipient@example.com')->send($mail);

    expect(true)->toBeTrue();
});

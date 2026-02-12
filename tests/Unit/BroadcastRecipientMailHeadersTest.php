<?php

use App\Mail\BroadcastRecipientMail;

it('includes list-unsubscribe headers', function () {
    $url = 'https://example.com/unsubscribe/1?signature=abc123';

    $mail = new BroadcastRecipientMail(
        subjectLine: 'Test Subject',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        messageMetadata: [],
        unsubscribeUrl: $url,
    );

    $headers = $mail->headers();

    expect($headers)->toHaveKey('List-Unsubscribe');
    expect($headers)->toHaveKey('List-Unsubscribe-Post');
    expect($headers['List-Unsubscribe'])->toBe('<'.$url.'>');
    expect($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
});

it('returns empty headers when unsubscribe url is empty', function () {
    $mail = new BroadcastRecipientMail(
        subjectLine: 'Test Subject',
        htmlContent: '<p>Test</p>',
        fromName: 'Sender',
        fromEmail: 'sender@example.com',
        replyToAddress: 'reply@example.com',
        messageMetadata: [],
        unsubscribeUrl: '',
    );

    $headers = $mail->headers();

    expect($headers)->toBe([]);
});

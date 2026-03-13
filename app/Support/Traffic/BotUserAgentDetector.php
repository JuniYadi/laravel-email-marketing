<?php

namespace App\Support\Traffic;

class BotUserAgentDetector
{
    /**
     * @var list<string>
     */
    protected array $botUserAgentSignatures = [
        'googlebot',
        'bingbot',
        'yandexbot',
        'duckduckbot',
        'baiduspider',
        'applebot',
        'crawler',
        'spider',
        'slurp',
        'bingpreview',
        'facebookexternalhit',
        'twitterbot',
        'linkedinbot',
        'whatsapp',
        'telegrambot',
        'slackbot',
        'discordbot',
        'bot',
    ];

    public function isBot(?string $userAgent): bool
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return false;
        }

        $normalizedUserAgent = strtolower($userAgent);

        foreach ($this->botUserAgentSignatures as $signature) {
            if (str_contains($normalizedUserAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
}

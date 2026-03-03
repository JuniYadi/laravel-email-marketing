<?php

namespace App\Support\Contacts;

use App\Models\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContactVariableRegistry
{
    /**
     * @var list<string>
     */
    public const SYSTEM_TEMPLATE_VARIABLE_KEYS = [
        'first_name',
        'last_name',
        'full_name',
        'email',
        'company',
        'unsubscribe_url',
    ];

    /**
     * @var list<string>
     */
    private const RESERVED_IMPORT_KEYS = [
        'first_name',
        'last_name',
        'full_name',
        'is_invalid',
        'groups',
    ];

    /**
     * @return list<string>
     */
    public static function templateVariableKeys(): array
    {
        return [
            ...self::SYSTEM_TEMPLATE_VARIABLE_KEYS,
            ...self::discoveredCustomFieldKeys(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function reservedCustomFieldKeys(): array
    {
        return array_values(array_unique([
            ...self::SYSTEM_TEMPLATE_VARIABLE_KEYS,
            ...self::RESERVED_IMPORT_KEYS,
        ]));
    }

    public static function normalizeCustomFieldKey(string $header): string
    {
        return (string) Str::of($header)
            ->trim()
            ->snake()
            ->replaceMatches('/[^a-z0-9_]+/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_');
    }

    /**
     * @return list<string>
     */
    public static function discoveredCustomFieldKeys(): array
    {
        $keys = [];

        Contact::query()
            ->select('id', 'custom_fields')
            ->whereNotNull('custom_fields')
            ->orderBy('id')
            ->chunkById(500, function (Collection $contacts) use (&$keys): void {
                foreach ($contacts as $contact) {
                    if (! is_array($contact->custom_fields)) {
                        continue;
                    }

                    foreach (array_keys($contact->custom_fields) as $key) {
                        if (! is_string($key) || $key === '') {
                            continue;
                        }

                        $keys[] = $key;
                    }
                }
            });

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, scalar|null>
     */
    public static function previewVariables(): array
    {
        $variables = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'company' => 'Acme',
            'unsubscribe_url' => 'https://example.com/unsubscribe',
        ];

        foreach (self::discoveredCustomFieldKeys() as $key) {
            $variables[$key] = (string) Str::of($key)->replace('_', ' ')->title();
        }

        return $variables;
    }
}

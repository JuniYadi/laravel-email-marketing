<?php

namespace App\Support\LandingPages;

use App\Models\LandingPageTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class LandingPageTemplateRegistry
{
    /**
     * @var list<string>
     */
    protected array $allowedFieldTypes = [
        'text',
        'textarea',
        'richtext',
        'color',
        'image_url',
        'url',
        'number',
        'select',
        'toggle',
    ];

    /**
     * @return array{synced: int, deactivated: int}
     */
    public function sync(): array
    {
        $definitions = $this->definitions();
        $activeKeys = [];

        foreach ($definitions as $definition) {
            LandingPageTemplate::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'view_path' => $definition['view_path'],
                    'schema' => $definition['schema'],
                    'preview_image_url' => $definition['preview_image_url'],
                    'is_active' => true,
                    'version' => $definition['version'],
                ],
            );

            $activeKeys[] = $definition['key'];
        }

        $deactivationQuery = LandingPageTemplate::query()->where('is_active', true);

        if ($activeKeys !== []) {
            $deactivationQuery->whereNotIn('key', $activeKeys);
        }

        $deactivated = $deactivationQuery->update(['is_active' => false]);

        return [
            'synced' => count($activeKeys),
            'deactivated' => $deactivated,
        ];
    }

    /**
     * @return list<array{key: string, name: string, description: ?string, view_path: string, schema: array<string, mixed>, preview_image_url: ?string, version: int}>
     */
    public function definitions(): array
    {
        $basePath = resource_path('views/landing-page-templates');

        if (! File::exists($basePath)) {
            return [];
        }

        $definitions = [];

        foreach (File::directories($basePath) as $directory) {
            $metaPath = $directory.'/template.json';
            $viewPath = $directory.'/view.blade.php';

            if (! File::exists($metaPath) || ! File::exists($viewPath)) {
                continue;
            }

            $folderKey = basename($directory);

            try {
                $rawMeta = json_decode(File::get($metaPath), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Invalid JSON in '.$metaPath, previous: $exception);
            }

            if (! is_array($rawMeta)) {
                throw new InvalidArgumentException('Invalid metadata payload in '.$metaPath);
            }

            $definitions[] = $this->normalizeDefinition($rawMeta, $folderKey);
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{key: string, name: string, description: ?string, view_path: string, schema: array<string, mixed>, preview_image_url: ?string, version: int}
     */
    protected function normalizeDefinition(array $definition, string $folderKey): array
    {
        $key = (string) ($definition['key'] ?? $folderKey);

        if (! preg_match('/^[a-z0-9\-]+$/', $key)) {
            throw new InvalidArgumentException('Invalid template key ['.$key.']');
        }

        $name = trim((string) ($definition['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Template ['.$key.'] must define a non-empty name');
        }

        $schema = $definition['schema'] ?? [];

        if (! is_array($schema)) {
            throw new InvalidArgumentException('Template ['.$key.'] has invalid schema payload');
        }

        $fields = $schema['fields'] ?? [];

        if (! is_array($fields)) {
            throw new InvalidArgumentException('Template ['.$key.'] schema fields must be an array');
        }

        $normalizedFields = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                throw new InvalidArgumentException('Template ['.$key.'] contains malformed field entries');
            }

            $fieldKey = (string) ($field['key'] ?? '');
            $fieldLabel = (string) ($field['label'] ?? '');
            $type = (string) ($field['type'] ?? '');

            if ($fieldKey === '' || $fieldLabel === '') {
                throw new InvalidArgumentException('Template ['.$key.'] has fields missing key or label');
            }

            if (! in_array($type, $this->allowedFieldTypes, true)) {
                throw new InvalidArgumentException('Template ['.$key.'] field ['.$fieldKey.'] has unsupported type ['.$type.']');
            }

            $normalized = [
                'key' => $fieldKey,
                'label' => $fieldLabel,
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
            ];

            if (isset($field['default'])) {
                $normalized['default'] = $field['default'];
            }

            if (isset($field['min']) && is_numeric($field['min'])) {
                $normalized['min'] = (float) $field['min'];
            }

            if (isset($field['max']) && is_numeric($field['max'])) {
                $normalized['max'] = (float) $field['max'];
            }

            if ($type === 'select') {
                $options = $field['options'] ?? [];

                if (! is_array($options) || $options === []) {
                    throw new InvalidArgumentException('Template ['.$key.'] select field ['.$fieldKey.'] requires options');
                }

                $normalizedOptions = [];

                foreach ($options as $option) {
                    if (is_array($option)) {
                        $value = (string) ($option['value'] ?? '');
                        $label = (string) ($option['label'] ?? $value);
                    } else {
                        $value = (string) $option;
                        $label = (string) $option;
                    }

                    if ($value === '') {
                        continue;
                    }

                    $normalizedOptions[] = [
                        'value' => $value,
                        'label' => $label,
                    ];
                }

                if ($normalizedOptions === []) {
                    throw new InvalidArgumentException('Template ['.$key.'] select field ['.$fieldKey.'] has no valid options');
                }

                $normalized['options'] = $normalizedOptions;
            }

            $normalizedFields[] = $normalized;
        }

        return [
            'key' => $key,
            'name' => $name,
            'description' => Str::of((string) ($definition['description'] ?? ''))->trim()->value() ?: null,
            'view_path' => 'landing-page-templates.'.$key.'.view',
            'schema' => [
                'fields' => $normalizedFields,
            ],
            'preview_image_url' => isset($definition['preview_image_url'])
                ? Str::of((string) $definition['preview_image_url'])->trim()->value()
                : null,
            'version' => max(1, (int) ($definition['version'] ?? 1)),
        ];
    }
}

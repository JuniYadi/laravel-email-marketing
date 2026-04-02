<?php

namespace App\Support\LandingPages;

use App\Models\LandingPageTemplate;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Throwable;

class LandingPageTemplateRegistry
{
    protected const SYNC_LOCK_KEY = 'landing-pages:templates:sync-lock';

    protected const FINGERPRINT_CACHE_KEY_PREFIX = 'landing-pages:templates:fingerprint:v1:deactivate:';

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
        'repeater',
    ];

    /**
     * @return array{synced: int, deactivated: int}
     */
    public function sync(bool $deactivateMissing = true): array
    {
        $definitions = $this->definitions();
        $activeKeys = [];
        $deactivated = 0;
        $connection = LandingPageTemplate::query()->getConnection();

        $connection->transaction(function () use ($definitions, $deactivateMissing, &$activeKeys, &$deactivated): void {
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

            if ($deactivateMissing) {
                $deactivationQuery = LandingPageTemplate::query()->where('is_active', true);

                if ($activeKeys !== []) {
                    $deactivationQuery->whereNotIn('key', $activeKeys);
                }

                $deactivated = $deactivationQuery->update(['is_active' => false]);
            }
        });

        return [
            'synced' => count($activeKeys),
            'deactivated' => $deactivated,
        ];
    }

    /**
     * @return array{synced: int, deactivated: int, skipped: bool}
     */
    public function syncIfChanged(bool $deactivateMissing = true): array
    {
        $fingerprint = $this->filesystemFingerprint();
        $cacheKey = $this->fingerprintCacheKey($deactivateMissing);
        $cachedFingerprint = Cache::get($cacheKey);

        if ($cachedFingerprint === $fingerprint) {
            return [
                'synced' => 0,
                'deactivated' => 0,
                'skipped' => true,
            ];
        }

        return $this->synchronizeWithLock(function () use ($deactivateMissing, $cacheKey): array {
            $latestFingerprint = $this->filesystemFingerprint();
            $latestCachedFingerprint = Cache::get($cacheKey);

            if ($latestCachedFingerprint === $latestFingerprint) {
                return [
                    'synced' => 0,
                    'deactivated' => 0,
                    'skipped' => true,
                ];
            }

            $result = $this->sync($deactivateMissing);
            Cache::forever($cacheKey, $latestFingerprint);

            return [
                'synced' => $result['synced'],
                'deactivated' => $result['deactivated'],
                'skipped' => false,
            ];
        });
    }

    protected function filesystemFingerprint(): string
    {
        $basePath = resource_path('views/landing-page-templates');

        if (! File::exists($basePath)) {
            return 'missing';
        }

        $directories = File::directories($basePath);
        sort($directories);

        $hash = hash_init('sha256');

        foreach ($directories as $directory) {
            $templateKey = basename($directory);
            hash_update($hash, $templateKey."\n");

            foreach (['template.json', 'view.blade.php'] as $fileName) {
                $filePath = $directory.'/'.$fileName;
                hash_update($hash, $fileName.':');

                if (! File::exists($filePath)) {
                    hash_update($hash, "missing\n");

                    continue;
                }

                hash_update($hash, (string) File::get($filePath));
                hash_update($hash, "\n");
            }
        }

        return hash_final($hash);
    }

    protected function fingerprintCacheKey(bool $deactivateMissing): string
    {
        return self::FINGERPRINT_CACHE_KEY_PREFIX.($deactivateMissing ? '1' : '0');
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    protected function synchronizeWithLock(callable $callback): mixed
    {
        try {
            return Cache::lock(self::SYNC_LOCK_KEY, 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            return $callback();
        } catch (Throwable) {
            return $callback();
        }
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
        $renderMode = (string) ($definition['render_mode'] ?? 'app');

        if (! in_array($renderMode, ['app', 'standalone'], true)) {
            throw new InvalidArgumentException('Template ['.$key.'] has invalid render_mode ['.$renderMode.']');
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                throw new InvalidArgumentException('Template ['.$key.'] contains malformed field entries');
            }

            $normalizedFields[] = $this->normalizeField($field, $key);
        }

        return [
            'key' => $key,
            'name' => $name,
            'description' => Str::of((string) ($definition['description'] ?? ''))->trim()->value() ?: null,
            'view_path' => 'landing-page-templates.'.$key.'.view',
            'schema' => [
                'fields' => $normalizedFields,
                'meta' => [
                    'render_mode' => $renderMode,
                ],
            ],
            'preview_image_url' => isset($definition['preview_image_url'])
                ? Str::of((string) $definition['preview_image_url'])->trim()->value()
                : null,
            'version' => max(1, (int) ($definition['version'] ?? 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function normalizeField(array $field, string $templateKey, string $parentPath = ''): array
    {
        $fieldKey = (string) ($field['key'] ?? '');
        $fieldLabel = (string) ($field['label'] ?? '');
        $type = (string) ($field['type'] ?? '');

        if ($fieldKey === '' || $fieldLabel === '') {
            throw new InvalidArgumentException('Template ['.$templateKey.'] has fields missing key or label');
        }

        if (! in_array($type, $this->allowedFieldTypes, true)) {
            throw new InvalidArgumentException('Template ['.$templateKey.'] field ['.$this->fullFieldPath($parentPath, $fieldKey).'] has unsupported type ['.$type.']');
        }

        $normalized = [
            'key' => $fieldKey,
            'label' => $fieldLabel,
            'type' => $type,
            'required' => (bool) ($field['required'] ?? false),
        ];

        if (isset($field['default'])) {
            if ($type === 'repeater' && ! is_array($field['default'])) {
                throw new InvalidArgumentException('Template ['.$templateKey.'] repeater field ['.$this->fullFieldPath($parentPath, $fieldKey).'] default must be an array');
            }

            $normalized['default'] = $field['default'];
        }

        if (isset($field['min']) && is_numeric($field['min'])) {
            $normalized['min'] = (float) $field['min'];
        }

        if (isset($field['max']) && is_numeric($field['max'])) {
            $normalized['max'] = (float) $field['max'];
        }

        if ($type === 'select') {
            $normalized['options'] = $this->normalizeSelectOptions($field, $templateKey, $parentPath);
        }

        if ($type === 'repeater') {
            $nestedFields = $field['fields'] ?? [];

            if (! is_array($nestedFields) || $nestedFields === []) {
                throw new InvalidArgumentException('Template ['.$templateKey.'] repeater field ['.$this->fullFieldPath($parentPath, $fieldKey).'] requires nested fields');
            }

            $normalizedNestedFields = [];

            foreach ($nestedFields as $nestedField) {
                if (! is_array($nestedField)) {
                    throw new InvalidArgumentException('Template ['.$templateKey.'] repeater field ['.$this->fullFieldPath($parentPath, $fieldKey).'] contains malformed nested fields');
                }

                $normalizedNestedFields[] = $this->normalizeField(
                    $nestedField,
                    $templateKey,
                    $this->fullFieldPath($parentPath, $fieldKey),
                );
            }

            $normalized['fields'] = $normalizedNestedFields;
            $normalized['default'] = is_array($normalized['default'] ?? null) ? $normalized['default'] : [];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array{value: string, label: string}>
     */
    protected function normalizeSelectOptions(array $field, string $templateKey, string $parentPath = ''): array
    {
        $fieldKey = (string) ($field['key'] ?? '');
        $options = $field['options'] ?? [];

        if (! is_array($options) || $options === []) {
            throw new InvalidArgumentException('Template ['.$templateKey.'] select field ['.$this->fullFieldPath($parentPath, $fieldKey).'] requires options');
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
            throw new InvalidArgumentException('Template ['.$templateKey.'] select field ['.$this->fullFieldPath($parentPath, $fieldKey).'] has no valid options');
        }

        return $normalizedOptions;
    }

    protected function fullFieldPath(string $parentPath, string $fieldKey): string
    {
        return $parentPath === '' ? $fieldKey : $parentPath.'.'.$fieldKey;
    }
}

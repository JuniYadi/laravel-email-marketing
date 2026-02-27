<?php

namespace App\Support\LandingPages;

use InvalidArgumentException;

class LandingPageRenderer
{
    /**
     * @param  array<string, mixed>  $templateSnapshot
     * @param  array<string, mixed>  $formData
     */
    public function render(array $templateSnapshot, array $formData): string
    {
        $viewPath = (string) ($templateSnapshot['view_path'] ?? '');

        if ($viewPath === '') {
            throw new InvalidArgumentException('Missing landing page template view path.');
        }

        $schema = $templateSnapshot['schema'] ?? [];

        if (! is_array($schema)) {
            $schema = [];
        }

        $safeData = $this->sanitizeData($schema, $formData);

        return view($viewPath, ['data' => $safeData])->render();
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    protected function sanitizeData(array $schema, array $formData): array
    {
        $safeData = [];
        $fields = $schema['fields'] ?? [];

        if (! is_array($fields)) {
            return $safeData;
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($key === '') {
                continue;
            }

            $value = $formData[$key] ?? ($field['default'] ?? null);

            if (in_array($type, ['text', 'textarea', 'select'], true)) {
                $safeData[$key] = is_scalar($value) ? (string) $value : '';

                continue;
            }

            if ($type === 'richtext') {
                $safeData[$key] = is_scalar($value)
                    ? strip_tags((string) $value, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><blockquote>')
                    : '';

                continue;
            }

            if ($type === 'color') {
                $candidate = is_scalar($value) ? (string) $value : '';
                $safeData[$key] = preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $candidate) === 1
                    ? $candidate
                    : '#111827';

                continue;
            }

            if (in_array($type, ['image_url', 'url'], true)) {
                $candidate = is_scalar($value) ? (string) $value : '';
                $safeData[$key] = filter_var($candidate, FILTER_VALIDATE_URL) !== false ? $candidate : '';

                continue;
            }

            if ($type === 'number') {
                $safeData[$key] = is_numeric($value) ? $value + 0 : 0;

                continue;
            }

            if ($type === 'toggle') {
                $safeData[$key] = (bool) $value;
            }
        }

        return $safeData;
    }
}

<?php

namespace App\Support\LandingPages;

use DOMDocument;
use DOMElement;
use DOMNode;
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

            if ($key === '') {
                continue;
            }

            $value = $formData[$key] ?? ($field['default'] ?? null);
            $safeData[$key] = $this->sanitizeFieldValue($field, $value);
        }

        return $safeData;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function sanitizeFieldValue(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        if (in_array($type, ['text', 'textarea', 'select'], true)) {
            return is_scalar($value) ? strip_tags((string) $value) : '';
        }

        if ($type === 'richtext') {
            return is_scalar($value)
                ? $this->sanitizeRichText((string) $value)
                : '';
        }

        if ($type === 'color') {
            $candidate = is_scalar($value) ? (string) $value : '';

            return preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $candidate) === 1
                ? $candidate
                : '#111827';
        }

        if (in_array($type, ['image_url', 'url'], true)) {
            $candidate = is_scalar($value) ? (string) $value : '';

            return filter_var($candidate, FILTER_VALIDATE_URL) !== false ? $candidate : '';
        }

        if ($type === 'number') {
            return is_numeric($value) ? $value + 0 : 0;
        }

        if ($type === 'toggle') {
            return (bool) $value;
        }

        if ($type === 'repeater') {
            return $this->sanitizeRepeaterValue($field, $value);
        }

        return is_scalar($value) ? strip_tags((string) $value) : '';
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array<string, mixed>>
     */
    protected function sanitizeRepeaterValue(array $field, mixed $value): array
    {
        $items = is_array($value) ? array_values($value) : [];
        $nestedFields = $field['fields'] ?? [];

        if (! is_array($nestedFields) || $nestedFields === []) {
            return [];
        }

        $sanitizedItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sanitizedItem = [];

            foreach ($nestedFields as $nestedField) {
                if (! is_array($nestedField)) {
                    continue;
                }

                $nestedKey = (string) ($nestedField['key'] ?? '');

                if ($nestedKey === '') {
                    continue;
                }

                $nestedValue = $item[$nestedKey] ?? ($nestedField['default'] ?? null);
                $sanitizedItem[$nestedKey] = $this->sanitizeFieldValue($nestedField, $nestedValue);
            }

            $sanitizedItems[] = $sanitizedItem;
        }

        return $sanitizedItems;
    }

    protected function sanitizeRichText(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        $stripped = strip_tags($trimmed, '<p><div><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><blockquote>');
        $document = new DOMDocument('1.0', 'UTF-8');
        $encoded = mb_convert_encoding('<div>'.$stripped.'</div>', 'HTML-ENTITIES', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($encoded, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if ($loaded !== true) {
            return '';
        }

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        $allowedTags = [
            'p',
            'div',
            'br',
            'strong',
            'em',
            'ul',
            'ol',
            'li',
            'a',
            'h1',
            'h2',
            'h3',
            'h4',
            'blockquote',
        ];

        $this->sanitizeRichTextNode($root, $allowedTags);

        $output = '';

        foreach ($root->childNodes as $childNode) {
            $output .= $document->saveHTML($childNode);
        }

        return $output;
    }

    /**
     * @param  list<string>  $allowedTags
     */
    protected function sanitizeRichTextNode(DOMNode $node, array $allowedTags): void
    {
        /** @var array<int, DOMNode> $childNodes */
        $childNodes = [];

        foreach ($node->childNodes as $childNode) {
            $childNodes[] = $childNode;
        }

        foreach ($childNodes as $childNode) {
            if (! $childNode instanceof DOMElement) {
                continue;
            }

            $tagName = strtolower($childNode->tagName);

            if (! in_array($tagName, $allowedTags, true)) {
                $node->replaceChild($node->ownerDocument->createTextNode($childNode->textContent), $childNode);

                continue;
            }

            $allowedAttributes = $tagName === 'a' ? ['href'] : [];

            /** @var array<int, string> $attributeNames */
            $attributeNames = [];

            foreach ($childNode->attributes as $attribute) {
                $attributeNames[] = $attribute->name;
            }

            foreach ($attributeNames as $attributeName) {
                if (! in_array($attributeName, $allowedAttributes, true)) {
                    $childNode->removeAttribute($attributeName);
                }
            }

            if ($tagName === 'a' && $childNode->hasAttribute('href')) {
                $href = trim($childNode->getAttribute('href'));
                $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

                if ($href === '' || ! in_array($scheme, ['http', 'https', 'mailto'], true)) {
                    $childNode->removeAttribute('href');
                }
            }

            $this->sanitizeRichTextNode($childNode, $allowedTags);
        }
    }
}

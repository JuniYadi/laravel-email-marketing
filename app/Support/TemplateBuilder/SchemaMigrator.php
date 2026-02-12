<?php

namespace App\Support\TemplateBuilder;

class SchemaMigrator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    public function migrate(array $schema): ?array
    {
        if (($schema['schema_version'] ?? null) === 2 && is_array($schema['rows'] ?? null)) {
            return $this->normalizeV2($schema);
        }

        if (($schema['schema_version'] ?? null) !== 1 || ! is_array($schema['blocks'] ?? null)) {
            return null;
        }

        return $this->migrateFromV1($schema);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function normalizeV2(array $schema): array
    {
        return [
            'schema_version' => 2,
            'meta' => [
                'template_name' => (string) (($schema['meta']['template_name'] ?? '') ?: ''),
                'template_key' => (string) (($schema['meta']['template_key'] ?? 'blank') ?: 'blank'),
            ],
            'theme' => is_array($schema['theme'] ?? null) ? $schema['theme'] : [],
            'rows' => collect($schema['rows'])
                ->filter(fn (mixed $row): bool => is_array($row) && is_array($row['columns'] ?? null))
                ->map(function (array $row): array {
                    return [
                        'id' => (string) ($row['id'] ?? (string) str()->ulid()),
                        'style' => is_array($row['style'] ?? null) ? $row['style'] : [],
                        'columns' => collect($row['columns'])
                            ->filter(fn (mixed $column): bool => is_array($column))
                            ->map(function (array $column): array {
                                return [
                                    'id' => (string) ($column['id'] ?? (string) str()->ulid()),
                                    'width' => (string) ($column['width'] ?? '100%'),
                                    'elements' => collect($column['elements'] ?? [])
                                        ->filter(fn (mixed $element): bool => is_array($element))
                                        ->map(fn (array $element): array => [
                                            'id' => (string) ($element['id'] ?? (string) str()->ulid()),
                                            'type' => (string) ($element['type'] ?? 'text'),
                                            'content' => is_array($element['content'] ?? null) ? $element['content'] : [],
                                            'style' => is_array($element['style'] ?? null) ? $element['style'] : [],
                                            'visibility' => is_array($element['visibility'] ?? null)
                                                ? $element['visibility']
                                                : ['desktop' => true, 'mobile' => true],
                                        ])
                                        ->values()
                                        ->all(),
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function migrateFromV1(array $schema): array
    {
        $rows = [];

        foreach ($schema['blocks'] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];

            if (in_array($type, ['two_column_media_text', 'image_text'], true)) {
                $rows[] = [
                    'id' => (string) str()->ulid(),
                    'style' => [],
                    'columns' => [
                        [
                            'id' => (string) str()->ulid(),
                            'width' => '50%',
                            'elements' => [[
                                'id' => (string) str()->ulid(),
                                'type' => 'image',
                                'content' => [
                                    'url' => (string) ($content['image_url'] ?? ''),
                                    'alt' => (string) ($content['title'] ?? 'Image'),
                                ],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ]],
                        ],
                        [
                            'id' => (string) str()->ulid(),
                            'width' => '50%',
                            'elements' => [[
                                'id' => (string) str()->ulid(),
                                'type' => 'text',
                                'content' => [
                                    'text' => trim((string) ($content['title'] ?? ''))."\n\n".trim((string) ($content['body'] ?? '')),
                                ],
                                'style' => [],
                                'visibility' => ['desktop' => true, 'mobile' => true],
                            ]],
                        ],
                    ],
                ];

                continue;
            }

            $elements = $this->mapV1BlockToElements($type, $content);

            if ($elements === []) {
                continue;
            }

            $rows[] = [
                'id' => (string) str()->ulid(),
                'style' => [],
                'columns' => [
                    [
                        'id' => (string) str()->ulid(),
                        'width' => '100%',
                        'elements' => $elements,
                    ],
                ],
            ];
        }

        if ($rows === []) {
            return [
                'schema_version' => 2,
                'meta' => [
                    'template_name' => '',
                    'template_key' => 'blank',
                ],
                'theme' => is_array($schema['theme'] ?? null) ? $schema['theme'] : [],
                'rows' => [],
            ];
        }

        return [
            'schema_version' => 2,
            'meta' => [
                'template_name' => '',
                'template_key' => (string) ($schema['template_key'] ?? 'blank'),
            ],
            'theme' => is_array($schema['theme'] ?? null) ? $schema['theme'] : [],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, array{id: string, type: string, content: array<string, mixed>, style: array<string, mixed>, visibility: array<string, bool>}>
     */
    protected function mapV1BlockToElements(string $type, array $content): array
    {
        if ($type === 'hero') {
            $elements = [[
                'id' => (string) str()->ulid(),
                'type' => 'text',
                'content' => [
                    'text' => trim((string) ($content['headline'] ?? ''))."\n\n".trim((string) ($content['subheadline'] ?? '')),
                ],
                'style' => [
                    'font_size' => 24,
                    'font_weight' => 700,
                    'text_align' => (string) ($content['alignment'] ?? 'center'),
                ],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];

            if (trim((string) ($content['image_url'] ?? '')) !== '') {
                $elements[] = [
                    'id' => (string) str()->ulid(),
                    'type' => 'image',
                    'content' => [
                        'url' => (string) $content['image_url'],
                        'alt' => (string) ($content['image_alt'] ?? 'Hero image'),
                    ],
                    'style' => [],
                    'visibility' => ['desktop' => true, 'mobile' => true],
                ];
            }

            if (trim((string) ($content['cta_text'] ?? '')) !== '') {
                $elements[] = [
                    'id' => (string) str()->ulid(),
                    'type' => 'button',
                    'content' => [
                        'text' => (string) $content['cta_text'],
                        'url' => (string) ($content['cta_url'] ?? '#'),
                    ],
                    'style' => ['text_align' => (string) ($content['alignment'] ?? 'center')],
                    'visibility' => ['desktop' => true, 'mobile' => true],
                ];
            }

            return $elements;
        }

        if ($type === 'text') {
            return [[
                'id' => (string) str()->ulid(),
                'type' => 'text',
                'content' => ['text' => (string) ($content['text'] ?? '')],
                'style' => ['text_align' => (string) ($content['alignment'] ?? 'left')],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];
        }

        if ($type === 'image') {
            return [[
                'id' => (string) str()->ulid(),
                'type' => 'image',
                'content' => [
                    'url' => (string) ($content['url'] ?? $content['image_url'] ?? ''),
                    'alt' => (string) ($content['alt'] ?? 'Image'),
                ],
                'style' => [],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];
        }

        if (in_array($type, ['cta_button', 'button'], true)) {
            return [[
                'id' => (string) str()->ulid(),
                'type' => 'button',
                'content' => [
                    'text' => (string) ($content['text'] ?? 'Open'),
                    'url' => (string) ($content['url'] ?? '#'),
                ],
                'style' => ['text_align' => (string) ($content['alignment'] ?? 'center')],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];
        }

        if ($type === 'divider') {
            return [[
                'id' => (string) str()->ulid(),
                'type' => 'divider',
                'content' => [],
                'style' => [],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];
        }

        if ($type === 'spacer') {
            return [[
                'id' => (string) str()->ulid(),
                'type' => 'spacer',
                'content' => ['height' => (int) ($content['height'] ?? 24)],
                'style' => [],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];
        }

        if ($type === 'footer_unsubscribe') {
            return [[
                'id' => (string) str()->ulid(),
                'type' => 'text',
                'content' => [
                    'text' => trim((string) ($content['text'] ?? ''))."\n\n".((string) ($content['unsubscribe_label'] ?? 'Unsubscribe')).': '.((string) ($content['unsubscribe_url'] ?? '{{ unsubscribe_url }}')),
                ],
                'style' => [
                    'font_size' => 12,
                    'color' => '#6b7280',
                ],
                'visibility' => ['desktop' => true, 'mobile' => true],
            ]];
        }

        return [];
    }
}

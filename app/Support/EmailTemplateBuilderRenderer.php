<?php

namespace App\Support;

class EmailTemplateBuilderRenderer
{
    /**
     * @param  array{
     *     schema_version?: int,
     *     theme?: array<string, mixed>,
     *     rows?: array<int, array<string, mixed>>
     * }  $schema
     */
    public function render(array $schema): string
    {
        $theme = $this->normalizeTheme($schema['theme'] ?? []);
        $rows = is_array($schema['rows'] ?? null) ? $schema['rows'] : [];
        $contentWidth = (int) $theme['content_width'];
        $bodyRows = $this->renderRows($rows, $theme);

        return <<<HTML
<style>
@media only screen and (max-width: 600px) {
    .stack-column {
        display: block !important;
        width: 100% !important;
    }
}
</style>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin:0;padding:24px 0;background:{$theme['background_color']};font-family:{$theme['font_family']};">
    <tr>
        <td align="center" style="padding:0 12px;">
            <table role="presentation" width="{$contentWidth}" cellpadding="0" cellspacing="0" style="width:100%;max-width:{$contentWidth}px;background:{$theme['surface_color']};border:1px solid #e5e7eb;border-radius:10px;">
                <tr>
                    <td style="padding:24px;color:{$theme['text_color']};line-height:1.6;font-family:{$theme['font_family']};">
                        {$bodyRows}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string|int>  $theme
     */
    protected function renderRows(array $rows, array $theme): string
    {
        $html = '';

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];
            $rowStyle = is_array($row['style'] ?? null) ? $row['style'] : [];
            $paddingBottom = $this->integerValue($rowStyle, 'padding_bottom', (int) ($theme['section_spacing'] ?? 24), min: 0, max: 120);
            $paddingTop = $this->integerValue($rowStyle, 'padding_top', 0, min: 0, max: 120);
            $backgroundColor = e((string) ($rowStyle['background_color'] ?? 'transparent'));

            $columnsHtml = '';

            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }

                $width = (string) ($column['width'] ?? '100%');
                $columnWidth = in_array($width, ['50%', '100%'], true) ? $width : '100%';
                $elements = is_array($column['elements'] ?? null) ? $column['elements'] : [];
                $elementsHtml = $this->renderElements($elements, $theme);

                $columnsHtml .= <<<HTML
<td class="stack-column" width="{$columnWidth}" style="width:{$columnWidth};vertical-align:top;padding:0 8px;">
    {$elementsHtml}
</td>
HTML;
            }

            if ($columnsHtml === '') {
                continue;
            }

            $html .= <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0;padding:{$paddingTop}px 0 {$paddingBottom}px;background:{$backgroundColor};">
    <tr>
        {$columnsHtml}
    </tr>
</table>
HTML;
        }

        return $html;
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     * @param  array<string, string|int>  $theme
     */
    protected function renderElements(array $elements, array $theme): string
    {
        $html = '';

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? 'text');
            $content = is_array($element['content'] ?? null) ? $element['content'] : [];
            $style = is_array($element['style'] ?? null) ? $element['style'] : [];
            $visibility = is_array($element['visibility'] ?? null) ? $element['visibility'] : ['desktop' => true, 'mobile' => true];

            if (($visibility['desktop'] ?? true) === false && ($visibility['mobile'] ?? true) === false) {
                continue;
            }

            $marginTop = $this->integerValue($style, 'margin_top', 0, min: 0, max: 80);
            $marginBottom = $this->integerValue($style, 'margin_bottom', 16, min: 0, max: 120);
            $elementWrapper = 'padding:'.$marginTop.'px 0 '.$marginBottom.'px;';

            if ($type === 'text') {
                $text = $this->lineBreaks((string) ($content['text'] ?? ''));
                $fontSize = $this->integerValue($style, 'font_size', 16, min: 10, max: 56);
                $fontWeight = $this->integerValue($style, 'font_weight', 400, min: 100, max: 900);
                $lineHeight = $this->floatValue($style, 'line_height', 1.6, min: 1.0, max: 2.2);
                $align = $this->alignment((string) ($style['text_align'] ?? 'left'));
                $color = e((string) ($style['color'] ?? $theme['text_color']));
                $backgroundColor = e((string) ($style['background_color'] ?? 'transparent'));
                $padding = $this->paddingStyles($style);
                $border = $this->borderStyles($style);

                $html .= <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{$elementWrapper}">
    <tr>
        <td align="{$align}" style="font-size:{$fontSize}px;font-weight:{$fontWeight};line-height:{$lineHeight};color:{$color};background:{$backgroundColor};{$padding}{$border}">
            {$text}
        </td>
    </tr>
</table>
HTML;

                continue;
            }

            if ($type === 'image') {
                $url = $this->sanitizeUrl((string) ($content['url'] ?? ''));
                $alt = e((string) ($content['alt'] ?? 'Image'));
                $radius = $this->integerValue($style, 'border_radius', 8, min: 0, max: 32);
                $width = $this->integerValue($style, 'width_percent', 100, min: 10, max: 100);
                $align = $this->alignment((string) ($style['text_align'] ?? 'left'));

                $html .= <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{$elementWrapper}">
    <tr>
        <td align="{$align}">
            <img src="{$url}" alt="{$alt}" style="width:{$width}%;max-width:100%;height:auto;border-radius:{$radius}px;display:block;">
        </td>
    </tr>
</table>
HTML;

                continue;
            }

            if ($type === 'button') {
                $text = e((string) ($content['text'] ?? 'Open'));
                $url = $this->sanitizeUrl((string) ($content['url'] ?? '#'));
                $align = $this->alignment((string) ($style['text_align'] ?? 'left'));
                $bg = e((string) ($style['background_color'] ?? $theme['button_bg_color']));
                $color = e((string) ($style['color'] ?? $theme['button_text_color']));
                $radius = $this->integerValue($style, 'border_radius', 8, min: 0, max: 32);
                $paddingY = $this->integerValue($style, 'padding_top', 12, min: 6, max: 40);
                $paddingX = $this->integerValue($style, 'padding_left', 18, min: 8, max: 48);

                $html .= <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{$elementWrapper}">
    <tr>
        <td align="{$align}">
            <a href="{$url}" style="display:inline-block;background:{$bg};color:{$color};text-decoration:none;padding:{$paddingY}px {$paddingX}px;border-radius:{$radius}px;font-weight:600;">
                {$text}
            </a>
        </td>
    </tr>
</table>
HTML;

                continue;
            }

            if ($type === 'divider') {
                $dividerColor = e((string) ($style['border_color'] ?? '#e5e7eb'));
                $html .= <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{$elementWrapper}">
    <tr>
        <td style="border-top:1px solid {$dividerColor};font-size:0;line-height:0;">&nbsp;</td>
    </tr>
</table>
HTML;

                continue;
            }

            if ($type === 'spacer') {
                $height = $this->integerValue($content, 'height', 24, min: 4, max: 180);
                $html .= '<div style="height:'.$height.'px;line-height:'.$height.'px;font-size:0;">&nbsp;</div>';

                continue;
            }

            if ($type === 'social') {
                $items = is_array($content['items'] ?? null) ? $content['items'] : [];

                if ($items === []) {
                    continue;
                }

                $links = '';

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $label = e((string) ($item['label'] ?? 'Link'));
                    $url = $this->sanitizeUrl((string) ($item['url'] ?? '#'));
                    $links .= '<a href="'.$url.'" style="display:inline-block;margin:0 8px 8px 0;color:'.e((string) $theme['link_color']).';text-decoration:underline;">'.$label.'</a>';
                }

                $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="'.$elementWrapper.'"><tr><td>'.$links.'</td></tr></table>';
            }
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, string|int>
     */
    protected function normalizeTheme(array $theme): array
    {
        return [
            'content_width' => $this->integerValue($theme, 'content_width', 640, min: 480, max: 760),
            'font_family' => e((string) ($theme['font_family'] ?? 'Arial, sans-serif')),
            'background_color' => e((string) ($theme['background_color'] ?? '#f3f4f6')),
            'surface_color' => e((string) ($theme['surface_color'] ?? '#ffffff')),
            'text_color' => e((string) ($theme['text_color'] ?? '#1f2937')),
            'heading_color' => e((string) ($theme['heading_color'] ?? '#111827')),
            'link_color' => e((string) ($theme['link_color'] ?? '#2563eb')),
            'button_bg_color' => e((string) ($theme['button_bg_color'] ?? '#2563eb')),
            'button_text_color' => e((string) ($theme['button_text_color'] ?? '#ffffff')),
            'section_spacing' => $this->integerValue($theme, 'section_spacing', 24, min: 0, max: 80),
        ];
    }

    /**
     * @param  array<string, mixed>  $styles
     */
    protected function paddingStyles(array $styles): string
    {
        $top = $this->integerValue($styles, 'padding_top', 0, min: 0, max: 80);
        $right = $this->integerValue($styles, 'padding_right', 0, min: 0, max: 80);
        $bottom = $this->integerValue($styles, 'padding_bottom', 0, min: 0, max: 80);
        $left = $this->integerValue($styles, 'padding_left', 0, min: 0, max: 80);

        return 'padding:'.$top.'px '.$right.'px '.$bottom.'px '.$left.'px;';
    }

    /**
     * @param  array<string, mixed>  $styles
     */
    protected function borderStyles(array $styles): string
    {
        $borderWidth = $this->integerValue($styles, 'border_width', 0, min: 0, max: 8);
        $borderColor = e((string) ($styles['border_color'] ?? '#d1d5db'));
        $radius = $this->integerValue($styles, 'border_radius', 0, min: 0, max: 32);

        return 'border:'.$borderWidth.'px solid '.$borderColor.';border-radius:'.$radius.'px;';
    }

    protected function lineBreaks(string $value): string
    {
        return nl2br(e($value));
    }

    protected function sanitizeUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return '#';
        }

        if (preg_match('/^\{\{\s*[a-zA-Z0-9_]+\s*\}\}$/', $trimmed) === 1) {
            return e($trimmed);
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            return '#';
        }

        return e($trimmed);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function integerValue(array $values, string $key, int $default, int $min, int $max): int
    {
        $value = (int) ($values[$key] ?? $default);

        return max($min, min($max, $value));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function floatValue(array $values, string $key, float $default, float $min, float $max): float
    {
        $value = (float) ($values[$key] ?? $default);

        return max($min, min($max, $value));
    }

    protected function alignment(string $value): string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
    }
}

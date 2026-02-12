<?php

namespace App\Support;

class TemplateRenderer
{
    /**
     * Render template placeholders using provided variables.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function render(string $content, array $variables): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($variables): string {
            $key = $matches[1];

            if (! array_key_exists($key, $variables)) {
                return $matches[0];
            }

            $value = $variables[$key];

            return is_scalar($value) ? (string) $value : '';
        }, $content);
    }
}

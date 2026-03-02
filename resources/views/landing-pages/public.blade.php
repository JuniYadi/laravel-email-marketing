<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ $meta['title'] ?: $landingPage->title }}</title>
        <meta name="description" content="{{ $meta['description'] }}" />
        <meta property="og:title" content="{{ $meta['og_title'] ?: $meta['title'] ?: $landingPage->title }}" />
        <meta property="og:description" content="{{ $meta['og_description'] ?: $meta['description'] }}" />

        @if ($meta['og_image'] !== '')
            <meta property="og:image" content="{{ $meta['og_image'] }}" />
        @endif

        @if ((bool) $meta['noindex'])
            <meta name="robots" content="noindex, nofollow" />
        @endif

        @unless ($isStandaloneTemplate ?? false)
            @vite('resources/css/app.css')
        @endunless

        @if ($isStandaloneTemplate ?? false)
            <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        @endif
    </head>
    <body @class([
        'min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100' => ! ($isStandaloneTemplate ?? false),
    ])>
        {!! $html !!}
    </body>
</html>

<div class="min-h-screen px-4 py-12 md:px-8" style="background-color: {{ $data['background_color'] ?? '#0F172A' }};">
    <section class="mx-auto max-w-3xl rounded-3xl bg-white p-8 text-zinc-900 shadow-xl md:p-10">
        @if (($data['show_badge'] ?? false) === true && ! empty($data['badge_text']))
            <p class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-700">
                {{ $data['badge_text'] }}
            </p>
        @endif

        <h1 class="mt-4 text-4xl font-semibold tracking-tight">{{ $data['headline'] ?? '' }}</h1>
        <p class="mt-4 text-zinc-600">{{ $data['body'] ?? '' }}</p>

        <a href="{{ $data['cta_url'] ?? '#' }}" class="mt-8 inline-flex rounded-xl bg-zinc-900 px-5 py-3 text-sm font-semibold text-white">
            {{ $data['cta_label'] ?? 'Get Started' }}
        </a>
    </section>
</div>

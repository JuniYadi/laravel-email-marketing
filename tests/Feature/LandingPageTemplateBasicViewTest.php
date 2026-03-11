<?php

test('basic template renders expected wrapper and content classes', function () {
    $html = view('landing-page-templates.basic.view', [
        'data' => [
            'headline' => 'Create your first landing page',
            'body' => 'This is the basic starter template.',
            'cta_label' => 'Get Started',
            'cta_url' => 'https://example.com',
            'background_color' => '#0F172A',
            'show_badge' => true,
            'badge_text' => 'Base Template',
        ],
    ])->render();

    expect($html)
        ->toContain('class="min-h-screen px-4 py-12 md:px-8"')
        ->toContain('background-color: #0F172A;')
        ->toContain('mx-auto max-w-3xl rounded-3xl bg-white p-8 text-zinc-900 shadow-xl md:p-10')
        ->toContain('mt-4 text-4xl font-semibold tracking-tight')
        ->toContain('mt-4 text-zinc-600')
        ->toContain('mt-8 inline-flex rounded-xl bg-zinc-900 px-5 py-3 text-sm font-semibold text-white');
});

test('basic template hides badge when show_badge is false', function () {
    $html = view('landing-page-templates.basic.view', [
        'data' => [
            'headline' => 'No badge headline',
            'body' => 'No badge body',
            'cta_label' => 'Get Started',
            'cta_url' => 'https://example.com',
            'background_color' => '#111827',
            'show_badge' => false,
            'badge_text' => 'Base Template',
        ],
    ])->render();

    expect($html)->not->toContain('inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-700');
});

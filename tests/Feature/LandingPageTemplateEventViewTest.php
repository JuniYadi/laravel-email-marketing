<?php

test('speaker card uses constrained image height on single-column layout', function () {
    $html = view('landing-page-templates.template-event.view', [
        'data' => [
            'speaker_image' => 'https://example.com/speaker.jpg',
            'speaker_name' => 'Martin Giese',
            'speaker_title' => 'Helping Startups as Coach, Advisor, Investor, & Board Member',
            'speaker_bio' => 'Sample bio',
            'program_description' => '<p><strong>Program</strong> body</p>',
            'event_format_details' => '<p><strong>Format:</strong> Workshop</p>',
            'modules_list' => '<ul><li>Module</li></ul>',
        ],
    ])->render();

    expect($html)
        ->toContain('Meet the Speaker')
        ->toContain('[&_strong]:text-[#a83021]')
        ->toContain('<strong>Format:</strong> Workshop')
        ->toContain('md:flex md:min-h-[450px] md:items-start')
        ->toContain('h-[280px] overflow-hidden rounded-lg bg-white sm:h-[320px] md:h-[400px] md:w-[300px] md:shrink-0')
        ->toContain('h-full w-full object-cover');
});

test('about footer renders the expected vector and image layout classes', function () {
    $html = view('landing-page-templates.template-event.view', [
        'data' => [
            'about_title' => 'About Us',
            'about_body' => "Line one\n\nLine two",
            'about_image' => 'https://example.com/footer-image.png',
            'program_description' => '<p><strong>Program</strong> body</p>',
            'event_format_details' => '<p><strong>Format:</strong> Workshop</p>',
            'modules_list' => '<ul><li>Module</li></ul>',
        ],
    ])->render();

    expect($html)
        ->toContain('relative mx-auto w-full max-w-[1440px] px-8 py-16 lg:h-[542px] lg:px-16 lg:py-0')
        ->toContain('grid grid-cols-1 items-center gap-10 lg:grid-cols-[392px_1fr] lg:items-start lg:pt-[115px]')
        ->toContain('text-[48px] font-black leading-[normal] text-[#f2f1f0]')
        ->toContain('mt-6 whitespace-pre-line text-[20px] leading-[normal] text-[#f2f1f0]')
        ->toContain('pointer-events-none absolute inset-0 z-0 hidden opacity-90 lg:block lg:translate-x-[88px]')
        ->toContain('relative z-10 h-full w-full overflow-hidden rounded-[24px] bg-[#f2f1f0] shadow-[0_0_4px_0_rgba(0,0,0,0.25)]')
        ->toContain('absolute left-1/2 top-1/2 h-full w-[532px] -translate-x-1/2 -translate-y-1/2 lg:h-[300px]');
});

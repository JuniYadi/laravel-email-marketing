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

test('about footer uses figma-aligned desktop and mobile geometry classes', function () {
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
        ->toContain('left-1/2 top-[423px] z-0 h-[677px] w-[391px] -translate-x-1/2 lg:hidden')
        ->toContain('right-[-334px] top-[31px] hidden h-[485px] w-[839px] lg:block')
        ->toContain('left-1/2 top-[36px] z-10 w-[350px] -translate-x-1/2 text-center font-serif text-[32px]')
        ->toContain('lg:left-[calc(30%-197px)] lg:top-[115px] lg:w-[392px]')
        ->toContain('left-1/2 top-[94px] z-10 w-[300px] -translate-x-1/2 whitespace-pre-line text-[16px]')
        ->toContain('left-1/2 top-[354px] z-10 h-[180px] w-[300px] -translate-x-1/2 overflow-hidden')
        ->toContain('lg:left-[calc(40%+129px)] lg:top-[123px] lg:h-[300px] lg:w-[500px] lg:translate-x-0');
});

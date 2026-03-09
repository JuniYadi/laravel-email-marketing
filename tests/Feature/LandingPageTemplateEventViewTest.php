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

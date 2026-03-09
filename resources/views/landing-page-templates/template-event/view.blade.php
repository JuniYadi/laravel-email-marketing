<div class="bg-[#f2f1f0] text-[#262626]">
    <section class="relative h-[844px] overflow-hidden bg-white md:h-[1024px]">
        <img
            src="{{ $data['hero_background_image'] ?? '' }}"
            alt="Event hero background"
            class="absolute inset-0 h-full w-full object-cover"
        >
        <div class="absolute inset-0 bg-[#262626]/75"></div>

        <div class="relative mx-auto h-full w-full max-w-[1440px]">
            <div class="hidden h-full md:block">
                <img
                    src="{{ $data['logo_image'] ?? '' }}"
                    alt="Logo"
                    class="absolute left-[150px] top-[75.68px] h-[42.64px] w-[226.63px] object-contain"
                >

                <div class="absolute left-[143px] top-[332.5px] flex h-[353px] w-[1153.53px] flex-col items-center gap-[30px] text-center">
                    <div class="h-[257px] w-full">
                        <h1 class="h-[179px] w-full text-[72px] font-black leading-[89.5px] text-white">
                            <span class="block">{{ $data['headline_text'] ?? '' }}</span>
                            <span class="block font-serif text-[72px] italic">{{ $data['headline_highlight'] ?? '' }}</span>
                        </h1>

                        <p class="w-full text-[32px] leading-[1.21875] text-[#f2f1f0]">
                            {{ $data['subheadline'] ?? '' }}
                        </p>
                    </div>

                    <a
                        href="{{ $data['cta_url'] ?? '#' }}"
                        class="inline-flex h-[66px] w-[255px] items-center justify-center rounded-[15px] bg-[#e0201b] text-[24.207px] font-bold leading-normal text-white transition hover:brightness-110"
                    >
                        {{ $data['cta_label'] ?? 'Register Now' }}
                    </a>
                </div>

                <div class="absolute left-[226px] top-[924px] flex h-[39px] w-[988px] items-center gap-[114px] whitespace-nowrap text-center text-[32px] font-bold leading-[39px] text-[#f2f1f0]">
                    <p class="leading-[39px]">{{ $data['hero_info_left'] ?? '' }}</p>
                    <p class="leading-[39px]">{{ $data['hero_info_middle'] ?? '' }}</p>
                    <p class="leading-[39px]">{{ $data['hero_info_right'] ?? '' }}</p>
                </div>
            </div>

            <div class="mx-auto block h-full w-full max-w-[390px] md:hidden">
                <img
                    src="{{ $data['logo_image'] ?? '' }}"
                    alt="Logo"
                    class="absolute left-5 top-[19px] h-[58px] w-[58px] object-contain"
                >

                <div class="absolute left-5 right-5 top-[304px] flex flex-col items-center gap-[15px] text-center">
                    <div class="w-full text-[#f2f1f0]">
                        <h1 class="text-[36px] font-black leading-[1.04] text-white">
                            <span class="block">{{ $data['headline_text'] ?? '' }}</span>
                            <span class="block font-serif text-[36px] italic">{{ $data['headline_highlight'] ?? '' }}</span>
                        </h1>

                        <p class="mt-2 text-[20px] leading-[1.2]">
                            {{ $data['subheadline'] ?? '' }}
                        </p>
                    </div>

                    <a
                        href="{{ $data['cta_url'] ?? '#' }}"
                        class="inline-flex h-[36.5px] w-[141px] items-center justify-center rounded-[8.294px] bg-[#e0201b] px-3 text-[13.385px] font-bold leading-normal text-[#f2f1f0] transition hover:brightness-110"
                    >
                        {{ $data['cta_label'] ?? 'Register Now' }}
                    </a>
                </div>

                <div class="absolute bottom-[34px] left-5 right-5 grid grid-cols-3 gap-[31px] text-center text-[16px] font-bold leading-[19px] text-[#f2f1f0]">
                    <p class="w-24">{{ $data['hero_info_left'] ?? '' }}</p>
                    <p class="w-24">{{ $data['hero_info_middle'] ?? '' }}</p>
                    <p class="w-24">{{ $data['hero_info_right'] ?? '' }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto grid w-full max-w-[1440px] grid-cols-1 items-center gap-8 px-8 py-16 md:grid-cols-2 lg:px-16">
        <div class="flex items-center justify-center">
            <div class="flex h-[58px] w-[200px] items-center justify-center bg-[#a83021] p-3">
                <img src="{{ $data['credibility_logo_image'] ?? '' }}" alt="Credibility logo" class="h-full w-full object-contain">
            </div>
        </div>
        <div class="flex items-center justify-center">
            <div class="flex h-[140px] w-[204px] items-center justify-center rounded-2xl bg-[#f5f5f5] px-4 text-center text-4xl font-black leading-tight text-black">
                {{ $data['partner_logo_label'] ?? '' }}
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-[1440px] px-8 pb-16 lg:px-16">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <article class="rounded-2xl bg-[#f2f1f0] p-8 shadow-[0_0_4px_0_rgba(0,0,0,0.25)]">
                <h2 class="text-[28px] font-black text-[#a83021]">Program Description</h2>
                <div class="mt-8 text-[20px] leading-[1.35] text-[#262626] [&_a]:text-[#262626] [&_a]:underline [&_a]:underline-offset-2 [&_blockquote]:border-s-4 [&_blockquote]:border-[#a83021]/30 [&_blockquote]:ps-4 [&_em]:italic [&_h1]:mb-4 [&_h1]:text-[28px] [&_h1]:font-black [&_h2]:mb-4 [&_h2]:text-[26px] [&_h2]:font-black [&_h3]:mb-3 [&_h3]:text-[24px] [&_h3]:font-black [&_h4]:mb-3 [&_h4]:text-[22px] [&_h4]:font-black [&_li]:leading-[1.3] [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:ps-6 [&_p]:mb-2 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_strong]:text-[#a83021] [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-6">
                    {!! $data['program_description'] ?? '' !!}
                </div>
            </article>

            <article class="rounded-2xl bg-[#f2f1f0] p-8 shadow-[0_0_4px_0_rgba(0,0,0,0.25)]">
                <h2 class="text-[28px] font-black text-[#a83021]">Event's Format</h2>
                <div class="mt-8 text-[20px] leading-[1.35] text-[#262626] [&_a]:text-[#262626] [&_a]:underline [&_a]:underline-offset-2 [&_blockquote]:border-s-4 [&_blockquote]:border-[#a83021]/30 [&_blockquote]:ps-4 [&_em]:italic [&_h1]:mb-4 [&_h1]:text-[28px] [&_h1]:font-black [&_h2]:mb-4 [&_h2]:text-[26px] [&_h2]:font-black [&_h3]:mb-3 [&_h3]:text-[24px] [&_h3]:font-black [&_h4]:mb-3 [&_h4]:text-[22px] [&_h4]:font-black [&_li]:leading-[1.3] [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:ps-6 [&_p]:mb-2 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_strong]:text-[#a83021] [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-6">
                    {!! $data['event_format_details'] ?? '' !!}
                </div>
            </article>

            <article class="rounded-2xl bg-[#f2f1f0] p-8 shadow-[0_0_4px_0_rgba(0,0,0,0.25)]">
                <h2 class="text-[28px] font-black text-[#a83021]">Modules</h2>
                <div class="mt-8 text-[20px] leading-[1.35] text-[#262626] [&_a]:text-[#262626] [&_a]:underline [&_a]:underline-offset-2 [&_blockquote]:border-s-4 [&_blockquote]:border-[#a83021]/30 [&_blockquote]:ps-4 [&_em]:italic [&_h1]:mb-4 [&_h1]:text-[28px] [&_h1]:font-black [&_h2]:mb-4 [&_h2]:text-[26px] [&_h2]:font-black [&_h3]:mb-3 [&_h3]:text-[24px] [&_h3]:font-black [&_h4]:mb-3 [&_h4]:text-[22px] [&_h4]:font-black [&_li]:leading-[1.3] [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:ps-6 [&_p]:mb-2 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_strong]:text-[#a83021] [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-6">
                    {!! $data['modules_list'] ?? '' !!}
                </div>
            </article>
        </div>
    </section>

    <section class="mx-auto w-full max-w-[1440px] px-8 pb-16 lg:px-16">
        <h2 class="text-center text-[48px] font-black text-[#a83021]">Meet the Speaker</h2>

        <article class="mx-auto mt-8 max-w-[910px] rounded-2xl bg-[#f2f1f0] p-5 shadow-[0_0_4px_0_rgba(0,0,0,0.25)] md:flex md:min-h-[450px] md:items-start md:gap-[34px] md:px-[21px] md:py-[23px]">
            <div class="h-[280px] overflow-hidden rounded-lg bg-white sm:h-[320px] md:h-[400px] md:w-[300px] md:shrink-0">
                <img src="{{ $data['speaker_image'] ?? '' }}" alt="Speaker" class="h-full w-full object-cover">
            </div>

            <div class="mt-5 md:mt-0 md:flex-1 md:pt-[3px]">
                <h3 class="font-serif text-[34px] leading-none italic text-[#262626] md:text-[36px]">{{ $data['speaker_name'] ?? '' }}</h3>
                <p class="mt-3 text-[22px] leading-tight font-bold text-black md:mt-2 md:text-[20px]">{{ $data['speaker_title'] ?? '' }}</p>
                <p class="mt-5 text-[20px] leading-[1.25] text-black md:mt-4">{{ $data['speaker_bio'] ?? '' }}</p>
            </div>
        </article>
    </section>

    <section class="relative overflow-hidden bg-[#a83021]">
        <div class="relative mx-auto w-full max-w-[1440px] px-8 py-16 lg:h-[542px] lg:px-16 lg:py-0">
            <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-[392px_1fr] lg:items-start lg:pt-[115px]">
                <div class="max-w-[392px]">
                    <h2 class="text-[48px] font-black leading-[normal] text-[#f2f1f0]">{{ $data['about_title'] ?? '' }}</h2>
                    <p class="mt-6 whitespace-pre-line text-[20px] leading-[normal] text-[#f2f1f0]">{{ $data['about_body'] ?? '' }}</p>
                </div>

                <div class="relative h-[260px] w-full max-w-[500px] justify-self-start lg:mr-[171px] lg:h-[300px] lg:w-[500px] lg:max-w-none lg:justify-self-end">
                    <div class="pointer-events-none absolute inset-0 z-0 hidden opacity-90 lg:block lg:translate-x-[88px]" aria-hidden="true">
                        <img src="https://www.figma.com/api/mcp/asset/96707261-e276-4f33-b610-35927257c46a" alt="" class="absolute left-[208px] top-[-90.5px] h-[256.25px] w-[835.039px] rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/80905673-acc8-4c38-8023-156b9b511815" alt="" class="absolute left-[225.6px] top-[-72.18px] h-[237.934px] w-[817.435px] rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/7bbdd959-a10a-424e-97e3-916acfc0f0b8" alt="" class="absolute left-[243.82px] top-[-53.47px] h-[218.859px] w-[799.22px] rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/3b326161-0198-4f7d-8d5e-4b77ee9a8af4" alt="" class="absolute left-[262.13px] top-[-34.8px] h-[200.546px] w-[780.907px] rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/3aecaee1-72d8-4293-9f99-fb9bfc63ee76" alt="" class="absolute left-[281.16px] top-[-16.48px] h-[181.694px] w-[761.876px] rotate-180">

                        <img src="https://www.figma.com/api/mcp/asset/96707261-e276-4f33-b610-35927257c46a" alt="" class="absolute left-[208px] top-[130.75px] h-[256.25px] w-[835.039px] -scale-y-100 rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/80905673-acc8-4c38-8023-156b9b511815" alt="" class="absolute left-[225.6px] top-[130.75px] h-[237.934px] w-[817.435px] -scale-y-100 rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/7bbdd959-a10a-424e-97e3-916acfc0f0b8" alt="" class="absolute left-[243.82px] top-[131.11px] h-[218.859px] w-[799.22px] -scale-y-100 rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/3b326161-0198-4f7d-8d5e-4b77ee9a8af4" alt="" class="absolute left-[262.13px] top-[130.75px] h-[200.546px] w-[780.907px] -scale-y-100 rotate-180">
                        <img src="https://www.figma.com/api/mcp/asset/3aecaee1-72d8-4293-9f99-fb9bfc63ee76" alt="" class="absolute left-[281.16px] top-[131.29px] h-[181.694px] w-[761.876px] -scale-y-100 rotate-180">
                    </div>

                    <div class="relative z-10 h-full w-full overflow-hidden rounded-[24px] bg-[#f2f1f0] shadow-[0_0_4px_0_rgba(0,0,0,0.25)]">
                        <div class="absolute left-1/2 top-1/2 h-full w-[532px] -translate-x-1/2 -translate-y-1/2 lg:h-[300px]">
                            <img src="{{ $data['about_image'] ?? '' }}" alt="About image" class="absolute inset-0 h-full w-full object-cover">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

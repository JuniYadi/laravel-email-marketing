<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Email Marketing Platform</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased">
    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-slate-950/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-2">
                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span class="font-semibold text-lg">{{ config('app.name') }}</span>
                </a>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">Features</a>
                    <a href="#pricing" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">Pricing</a>
                </nav>

                <!-- Auth Links -->
                <div class="flex items-center gap-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 dark:bg-blue-500 text-white hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors text-sm font-medium">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 dark:bg-blue-500 text-white hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors">
                                    Sign Up
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 dark:text-white mb-6">
                    Self-Hosted Email Marketing Platform
                </h1>
                <p class="text-lg sm:text-xl text-slate-600 dark:text-slate-400 mb-10 max-w-2xl mx-auto">
                    Create beautiful email campaigns, automate your marketing, and track results - all in one place. Open-source and self-hosted for complete control.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-6">
                    <a href="https://github.com/JuniYadi/laravel-email-marketing" target="_blank" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-medium hover:bg-slate-800 dark:hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        View on GitHub
                    </a>
                    <a href="https://github.com/JuniYadi/laravel-email-marketing/issues" target="_blank" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        Report an Issue
                    </a>
                </div>

                <p class="text-sm text-slate-500 dark:text-slate-500 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    100% Open Source
                </p>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">
                        Everything You Need to Succeed
                    </h2>
                    <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Powerful features to help you create, send, and track email campaigns with ease.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1: Campaign Builder -->
                    <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Campaign Builder</h3>
                        <p class="text-slate-600 dark:text-slate-400">Drag & drop editor for creating beautiful, responsive emails without any coding knowledge.</p>
                    </div>

                    <!-- Feature 2: Analytics & Tracking -->
                    <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Analytics & Tracking</h3>
                        <p class="text-slate-600 dark:text-slate-400">Track opens, clicks, and conversions in real-time with detailed reports and insights.</p>
                    </div>

                    <!-- Feature 3: Automation -->
                    <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Automation</h3>
                        <p class="text-slate-600 dark:text-slate-400">Set up automated email sequences and workflows to engage your audience 24/7.</p>
                    </div>

                    <!-- Feature 4: List Management -->
                    <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">List Management</h3>
                        <p class="text-slate-600 dark:text-slate-400">Organize subscribers into segments and manage your lists with powerful tools.</p>
                    </div>

                    <!-- Feature 5: Responsive Templates -->
                    <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">Responsive Templates</h3>
                        <p class="text-slate-600 dark:text-slate-400">Professionally designed templates that look great on any device or email client.</p>
                    </div>

                    <!-- Feature 6: A/B Testing -->
                    <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">A/B Testing</h3>
                        <p class="text-slate-600 dark:text-slate-400">Test subject lines, content, and send times to optimize your campaigns for better results.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Comparison Section -->
        <section id="pricing" class="py-20 px-4 sm:px-6 lg:px-8 bg-slate-50 dark:bg-slate-900">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">
                        Self-Host & Save Thousands
                    </h2>
                    <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Why pay monthly fees when you can own your platform? Compare our costs with popular providers.
                    </p>
                </div>

                <!-- Pricing Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th class="py-4 px-6 text-left text-sm font-semibold text-slate-600 dark:text-slate-400">Emails/Month</th>
                                <th class="py-4 px-6 text-center text-sm font-semibold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20">
                                    {{ config('app.name') }}*
                                </th>
                                <th class="py-4 px-6 text-center text-sm font-semibold text-slate-600 dark:text-slate-400">SendGrid</th>
                                <th class="py-4 px-6 text-center text-sm font-semibold text-slate-600 dark:text-slate-400">Mailchimp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-slate-200 dark:border-slate-800">
                                <td class="py-4 px-6 text-slate-900 dark:text-white font-medium">10,000</td>
                                <td class="py-4 px-6 text-center bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">$1</span>
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$14.95</td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$20+</td>
                            </tr>
                            <tr class="border-b border-slate-200 dark:border-slate-800">
                                <td class="py-4 px-6 text-slate-900 dark:text-white font-medium">50,000</td>
                                <td class="py-4 px-6 text-center bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">$5</span>
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$29.95</td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$50+</td>
                            </tr>
                            <tr class="border-b border-slate-200 dark:border-slate-800">
                                <td class="py-4 px-6 text-slate-900 dark:text-white font-medium">100,000</td>
                                <td class="py-4 px-6 text-center bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">$10</span>
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$49.95</td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$100+</td>
                            </tr>
                            <tr class="border-b border-slate-200 dark:border-slate-800">
                                <td class="py-4 px-6 text-slate-900 dark:text-white font-medium">500,000</td>
                                <td class="py-4 px-6 text-center bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">$50</span>
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$149+</td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$300+</td>
                            </tr>
                            <tr class="border-b border-slate-200 dark:border-slate-800">
                                <td class="py-4 px-6 text-slate-900 dark:text-white font-medium">1,000,000</td>
                                <td class="py-4 px-6 text-center bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">$100</span>
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$299+</td>
                                <td class="py-4 px-6 text-center text-slate-600 dark:text-slate-400">$500+</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pricing Notes -->
                <div class="mt-8 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-500 mb-4">
                        *{{ config('app.name') }} = Self-hosted (FREE software) + AWS SES sending fees ($0.10/1,000 emails)
                    </p>
                    <div class="flex flex-wrap justify-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-slate-700 dark:text-slate-300">Save up to 80%</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-slate-700 dark:text-slate-300">No vendor lock-in</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-slate-700 dark:text-slate-300">Your data on your server</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-blue-600 dark:bg-blue-700">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                    Ready to Get Started?
                </h2>
                <p class="text-lg text-blue-100 mb-8">
                    Self-host your own email marketing platform. Star us on GitHub and contribute to the project.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://github.com/JuniYadi/laravel-email-marketing" target="_blank" class="inline-flex items-center justify-center gap-2 px-8 py-4 text-lg font-medium rounded-lg bg-white text-blue-600 hover:bg-blue-50 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        Star on GitHub
                    </a>
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium rounded-lg border-2 border-white text-white hover:bg-white/10 transition-colors">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium rounded-lg border-2 border-white text-white hover:bg-white/10 transition-colors">
                                Login to Your Instance
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 bg-slate-900 dark:bg-slate-950 border-t border-slate-800">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                <!-- Logo -->
                <div class="flex items-center gap-2">
                    <svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span class="font-semibold text-lg text-white">{{ config('app.name') }}</span>
                </div>

                <!-- Footer Links -->
                <div class="flex flex-wrap justify-center gap-8 text-sm">
                    <div>
                        <h4 class="font-semibold text-white mb-3">Product</h4>
                        <ul class="space-y-2">
                            <li><a href="#features" class="text-slate-400 hover:text-white transition-colors">Features</a></li>
                            <li><a href="#pricing" class="text-slate-400 hover:text-white transition-colors">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white mb-3">Author</h4>
                        <ul class="space-y-2">
                            <li><a href="https://github.com/JuniYadi/laravel-email-marketing" target="_blank" class="text-slate-400 hover:text-white transition-colors inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                GitHub
                            </a></li>
                            <li><a href="https://github.com/JuniYadi" target="_blank" class="text-slate-400 hover:text-white transition-colors">@JuniYadi</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white mb-3">Legal</h4>
                        <ul class="space-y-2">
                            <li><a href="{{ route('legal.privacy') }}" class="text-slate-400 hover:text-white transition-colors">Privacy</a></li>
                            <li><a href="{{ route('legal.tos') }}" class="text-slate-400 hover:text-white transition-colors">Terms</a></li>
                            <li><a href="{{ route('legal.aup') }}" class="text-slate-400 hover:text-white transition-colors">Acceptable Use</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Copyright -->
            <div class="mt-8 pt-8 border-t border-slate-800 text-center">
                <p class="text-sm text-slate-500">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>

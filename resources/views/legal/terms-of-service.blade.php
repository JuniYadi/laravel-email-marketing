<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - {{ config('app.name') }}</title>
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
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span class="font-semibold text-lg">{{ config('app.name') }}</span>
                </a>
                <a href="{{ route('home') }}" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors text-sm font-medium">
                    &larr; Back to Home
                </a>
            </div>
        </div>
    </header>

    <main class="pt-24 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-8">Terms of Service</h1>
            <p class="text-slate-600 dark:text-slate-400 mb-8">Last updated: {{ date('F j, Y') }}</p>

            <div class="prose prose-slate dark:prose-invert max-w-none">
                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">1. Acceptance of Terms</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        By accessing and using {{ config('app.name') }} ("the Service"), you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to abide by these terms, please do not use this Service.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">2. Description of Service</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ config('app.name') }} is a self-hosted email marketing platform that provides tools for creating, sending, and tracking email campaigns. The Service is provided as open-source software that you host on your own infrastructure.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">3. User Responsibilities</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        As a user of this Service, you are responsible for:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li>Maintaining the security of your installation and user credentials</li>
                        <li>Ensuring compliance with applicable laws and regulations</li>
                        <li>Obtaining proper consent from email recipients</li>
                        <li>Managing your own infrastructure and hosting costs</li>
                        <li>Backing up your data regularly</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">4. Acceptable Use</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        You agree to use the Service only for lawful purposes and in accordance with these Terms. You must not use the Service to send spam, malicious content, or any communications that violate applicable laws. Please refer to our Acceptable Use Policy for more details.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">5. Intellectual Property</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        The software is provided under an open-source license. You are free to use, modify, and distribute the software in accordance with the terms of the applicable license. All third-party libraries and components retain their original licenses.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">6. Disclaimer of Warranties</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        THE SERVICE IS PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES, OR OTHER LIABILITY.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">7. Limitation of Liability</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        To the maximum extent permitted by law, the developers and contributors of this Service shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, or other intangible losses.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">8. Changes to Terms</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting. Your continued use of the Service following any changes indicates your acceptance of the new terms.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">9. Contact Information</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        For questions about these Terms of Service, please visit our GitHub repository at <a href="https://github.com/JuniYadi/laravel-email-marketing" class="text-blue-600 dark:text-blue-400 hover:underline">github.com/JuniYadi/laravel-email-marketing</a>.
                    </p>
                </section>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-8 px-4 sm:px-6 lg:px-8 bg-slate-900 dark:bg-slate-950 border-t border-slate-800">
        <div class="max-w-7xl mx-auto text-center">
            <p class="text-sm text-slate-500">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>

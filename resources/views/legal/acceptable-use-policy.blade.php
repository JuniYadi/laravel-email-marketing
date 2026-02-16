<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceptable Use Policy - {{ config('app.name') }}</title>
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
            <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-8">Acceptable Use Policy</h1>
            <p class="text-slate-600 dark:text-slate-400 mb-8">Last updated: {{ date('F j, Y') }}</p>

            <div class="prose prose-slate dark:prose-invert max-w-none">
                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">1. Purpose</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        This Acceptable Use Policy (AUP) outlines the guidelines and restrictions for using {{ config('app.name') }}. By using this Service, you agree to comply with this policy. Violations may result in termination of your right to use the software.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">2. Prohibited Activities</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        You may not use the Service to:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li>Send unsolicited commercial email (spam) or any form of unwanted communications</li>
                        <li>Distribute malware, viruses, or any malicious code</li>
                        <li>Engage in phishing, fraud, or deceptive practices</li>
                        <li>Harass, abuse, or harm other individuals or organizations</li>
                        <li>Violate any local, state, national, or international law</li>
                        <li>Infringe upon intellectual property rights of others</li>
                        <li>Send content that is illegal, defamatory, or obscene</li>
                        <li>Attempt to gain unauthorized access to any systems or networks</li>
                        <li>Interfere with or disrupt the Service or servers</li>
                        <li>Collect email addresses without proper consent</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">3. Email Sending Requirements</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        When using the Service to send emails, you must:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li>Obtain explicit consent from recipients before sending marketing emails</li>
                        <li>Include a clear and functional unsubscribe mechanism in every email</li>
                        <li>Provide accurate sender identification and contact information</li>
                        <li>Honor unsubscribe requests promptly (within 10 business days)</li>
                        <li>Comply with CAN-SPAM Act, GDPR, and other applicable email regulations</li>
                        <li>Maintain accurate records of consent for all subscribers</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">4. Content Guidelines</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        All content sent through the Service must be lawful, accurate, and not misleading. You are solely responsible for the content you create and distribute. Do not use the Service to distribute content that promotes illegal activities, discrimination, hate speech, or violence.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">5. Security Responsibilities</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        As a self-hosted platform, you are responsible for:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li>Keeping the software and dependencies up to date</li>
                        <li>Securing your server and database</li>
                        <li>Protecting user credentials and API keys</li>
                        <li>Implementing appropriate access controls</li>
                        <li>Regularly backing up your data</li>
                        <li>Monitoring for suspicious activity</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">6. Reporting Violations</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        If you become aware of any violations of this AUP, please report them through our GitHub repository. We take all reports seriously and will investigate any alleged violations.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">7. Consequences of Violations</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        Violation of this AUP may result in removal from community support channels, reporting to relevant authorities if required by law, and/or other actions as deemed appropriate. Since this is open-source software, you remain responsible for your own usage and compliance.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">8. Changes to Policy</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        We reserve the right to modify this AUP at any time. Changes will be posted on this page with an updated revision date. Continued use of the Service after changes constitutes acceptance of the modified policy.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">9. Contact Information</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        For questions about this Acceptable Use Policy, please visit our GitHub repository at <a href="https://github.com/JuniYadi/laravel-email-marketing" class="text-blue-600 dark:text-blue-400 hover:underline">github.com/JuniYadi/laravel-email-marketing</a>.
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

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - {{ config('app.name') }}</title>
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
            <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-8">Privacy Policy</h1>
            <p class="text-slate-600 dark:text-slate-400 mb-8">Last updated: {{ date('F j, Y') }}</p>

            <div class="prose prose-slate dark:prose-invert max-w-none">
                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">1. Introduction</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ config('app.name') }} is a self-hosted email marketing platform. This means you host and control the software on your own infrastructure. This Privacy Policy explains how the software handles data and your responsibilities as a self-hosted user.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">2. Self-Hosted Nature</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        Since {{ config('app.name') }} is self-hosted software, all data collected, stored, and processed by the application resides on your own servers and infrastructure. The developers and contributors of this software do not have access to any data you collect or process through your installation.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">3. Data Controller Responsibilities</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        As the operator of a self-hosted instance, you are the data controller and are solely responsible for:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li>Collecting and managing user and subscriber data in compliance with applicable laws</li>
                        <li>Implementing appropriate security measures to protect stored data</li>
                        <li>Providing privacy notices to your users and subscribers</li>
                        <li>Handling data subject requests (access, deletion, portability, etc.)</li>
                        <li>Ensuring compliance with GDPR, CCPA, and other applicable privacy regulations</li>
                        <li>Maintaining data backup and recovery procedures</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">4. Data Collected by the Software</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        The software is designed to collect and store the following types of data:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li><strong>User Account Data:</strong> Name, email address, password (hashed)</li>
                        <li><strong>Subscriber Data:</strong> Email addresses, names, custom fields, subscription preferences</li>
                        <li><strong>Campaign Data:</strong> Email content, sending history, engagement metrics</li>
                        <li><strong>Analytics Data:</strong> Open rates, click rates, bounce information</li>
                        <li><strong>System Logs:</strong> Error logs, activity logs for troubleshooting</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">5. Third-Party Services</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        If you configure the software to use third-party services (such as AWS SES for email delivery), those services will have access to certain data as necessary to provide their functions. You should review and comply with the privacy policies of any third-party services you integrate with your installation.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">6. Data Security</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        The software implements standard security practices including password hashing, CSRF protection, and SQL injection prevention. However, as a self-hosted solution, the overall security of your data depends on your server configuration, hosting environment, and operational practices.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">7. Data Retention</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        You have full control over data retention policies. The software does not automatically delete data unless configured to do so. You should establish and implement appropriate data retention policies based on your legal requirements and business needs.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">8. User Rights</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                        The software includes features to help you honor data subject rights, including:
                    </p>
                    <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-2">
                        <li>Unsubscribe functionality for email recipients</li>
                        <li>User account management and deletion</li>
                        <li>Contact and subscriber management capabilities</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">9. Cookies</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        The software uses cookies for session management and authentication. As a self-hosted operator, you are responsible for implementing appropriate cookie consent mechanisms as required by applicable laws.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">10. Children's Privacy</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        The software is not intended for use by children under the age of 13 (or the applicable age in your jurisdiction). As the operator, you should implement appropriate age verification measures if required by applicable laws.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">11. Changes to This Policy</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated revision date. As a self-hosted user, you are responsible for updating your own privacy policy to reflect any changes relevant to your installation.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">12. Contact Information</h2>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        For questions about this Privacy Policy or the software, please visit our GitHub repository at <a href="https://github.com/JuniYadi/laravel-email-marketing" class="text-blue-600 dark:text-blue-400 hover:underline">github.com/JuniYadi/laravel-email-marketing</a>.
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

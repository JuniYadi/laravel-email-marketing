<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <meta http-equiv="refresh" content="5;url={{ $redirectUrl }}">
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh items-center justify-center p-6 md:p-10">
            <main class="w-full max-w-lg rounded-2xl border border-zinc-200 bg-white/90 p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/80">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Redirect in progress</h1>
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    You will be redirected in 5 seconds to:
                </p>
                <p class="mt-2 break-all rounded-lg bg-zinc-100 px-3 py-2 font-mono text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    {{ $redirectUrl }}
                </p>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a
                        href="{{ $redirectUrl }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        Go now
                    </a>
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        Go to login
                    </a>
                </div>
            </main>
        </div>
        <script>
            window.setTimeout(() => {
                window.location.assign(@js($redirectUrl));
            }, 5000);
        </script>
    </body>
</html>

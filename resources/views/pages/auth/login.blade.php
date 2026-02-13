<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if(in_array(config('auth.mode'), ['google_only', 'both']))
            <!-- Google OAuth Button -->
            <flux:button 
                :href="route('auth.google.redirect')" 
                variant="outline" 
                class="w-full">
                <x-icons.google class="w-5 h-5" />
                {{ __('Continue with Google') }}
            </flux:button>

            @if(config('auth.mode') === 'both')
                <!-- Divider -->
                <div class="flex items-center gap-4">
                    <div class="flex-1 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('OR') }}</span>
                    <div class="flex-1 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
            @endif
        @endif

        @if(config('auth.mode') !== 'google_only')
            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
                @csrf

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <!-- Password -->
                <div class="relative">
                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    @if (Route::has('password.request'))
                        <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </flux:link>
                    @endif
                </div>

                <!-- Remember Me -->
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                        {{ __('Log in') }}
                    </flux:button>
                </div>
            </form>
        @endif

        @if (Route::has('register') && config('auth.mode') !== 'google_only')
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>

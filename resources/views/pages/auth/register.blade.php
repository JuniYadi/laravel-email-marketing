<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

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
            <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
                @csrf
                <!-- Name -->
                <flux:input
                    name="name"
                    :label="__('Name')"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('Full name')"
                />

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <!-- Password -->
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    viewable
                />

                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    viewable
                />

                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                        {{ __('Create account') }}
                    </flux:button>
                </div>
            </form>
        @endif

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>

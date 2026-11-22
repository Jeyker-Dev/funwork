<x-layouts::auth :title="__('Log in')">
    <x-auth-header :title="__('Pick up where you left off.')" :description="__('Log in to your FunWork workspace.')"/>

    <x-auth-session-status :status="session('status')"/>

    <x-auth.form method="POST" action="{{ route('login') }}">
        @csrf

        <flux:field>
            <flux:label>{{ __('Email address') }}</flux:label>

            <flux:input
                name="email"
                type="email"
                placeholder="name@example.com"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
                spellcheck="false"
            />

            <flux:error name="email"/>
        </flux:field>

        <flux:field>
            <div class="flex items-center justify-between gap-4">
                <flux:label>{{ __('Password') }}</flux:label>

                @if (Route::has('password.request'))
                    <flux:link class="text-sm font-medium text-accent-content! no-underline hover:underline" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <flux:input
                name="password"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <flux:error name="password"/>
        </flux:field>

        <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')"/>

        <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
            {{ __('Log in') }}
        </flux:button>
    </x-auth.form>
</x-layouts::auth>

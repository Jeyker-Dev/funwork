<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welcome Back')" :description="__('Log in to manage your workspace')"/>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')"/>

        <x-auth.form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <flux:field>
                <flux:label class="uppercase text-xs font-semibold!">{{ __('Email Address') }}</flux:label>

                <flux:input
                    name="email"
                    type="email"
                    placeholder="name@example.com"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="email"
                />

                <flux:error name="email"/>
            </flux:field>

            <!-- Password -->
            <div class="relative">

                <flux:field>
                    <flux:label class="uppercase text-xs font-semibold!">{{ __('Password') }}</flux:label>

                    <flux:input
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    <flux:error name="email"/>
                </flux:field>

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-xs inset-e-0 underline-none! text-orange-500" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')"/>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </x-auth.form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>

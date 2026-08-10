<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-auth.form method="POST" action="{{ route('register.store') }}">
            @csrf
            <!-- Name -->
            <flux:field>
                <flux:label class="uppercase text-xs font-semibold!">{{ __('Full Name') }}</flux:label>

                <flux:input
                    name="name"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('John Doe')"
                />

                <flux:error name="name"/>
            </flux:field>

            <!-- Username -->
            <flux:field>
                <flux:label class="uppercase text-xs font-semibold!">{{ __('Username') }}</flux:label>

                <flux:input
                    name="username"
                    :value="old('username')"
                    type="text"
                    required
                    autocomplete="username"
                    :placeholder="__('@johndoe')"
                />

                <flux:error name="username"/>
            </flux:field>

            <!-- Email Address -->
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
            <flux:field>
                <flux:label class="uppercase text-xs font-semibold!">{{ __('Password') }}</flux:label>

                <flux:input
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('********')"
                    viewable
                />

                <flux:error name="password"/>
            </flux:field>

            <!-- Confirm Password -->
            <flux:field>
                <flux:label class="uppercase text-xs font-semibold!">{{ __('Confirm Password') }}</flux:label>

                <flux:input
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('********')"
                    viewable
                />

                <flux:error name="password"/>
            </flux:field>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </x-auth.form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>

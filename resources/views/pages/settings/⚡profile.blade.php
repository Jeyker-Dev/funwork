<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public $photo;
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $timezone = 'UTC';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->username = Auth::user()->username ?? '';
        $this->email = Auth::user()->email;
        $this->timezone = Auth::user()->timezone ?? 'UTC';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $this->validate([
            'photo' => ['nullable', 'image'],
        ]);

        if ($this->photo) {
            $disk = config('filesystems.default');
            if ($user->profile_photo_path) {
                Storage::disk($disk)->delete($user->profile_photo_path);
            }
            $validated['profile_photo_path'] = Storage::disk($disk)->putFile('profile-photos', $this->photo);
            $this->photo = null;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            $disk = config('filesystems.default');
            Storage::disk($disk)->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
        }

        $this->photo = null;
        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<div class="w-full max-w-[1100px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-8">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout>
        @php
            $user = auth()->user();
            $avatarSrc = $photo ? $photo->temporaryUrl() : ($user->profile_photo_path ? $user->avatar_url : null);
        @endphp

        <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-6">
            <div>
                <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('Profile') }}
                </h2>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Manage your personal details and workspace identity.') }}
                </p>
            </div>

            <form wire:submit="updateProfileInformation" class="flex flex-col gap-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div
                        x-data="{ imageFailed: false }"
                        x-effect="imageFailed = false"
                        class="size-20 rounded-full bg-brand-strong text-white flex items-center justify-center font-headline font-bold text-2xl select-none shrink-0 ring-4 ring-surface-low overflow-hidden"
                    >
                        @if ($avatarSrc)
                            <img
                                src="{{ $avatarSrc }}"
                                alt="{{ $user->display_name }}"
                                class="size-full object-cover"
                                x-show="!imageFailed"
                                x-on:error="imageFailed = true"
                            />
                            <span x-show="imageFailed" x-cloak>{{ $user->initials() }}</span>
                        @else
                            <span>{{ $user->initials() }}</span>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2 min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <label
                                for="profile-photo-input"
                                class="inline-flex items-center justify-center px-4 py-2 min-h-11 sm:min-h-9 rounded-lg bg-surface-card border border-ghost text-ink hover:bg-surface-low text-sm font-medium transition-colors cursor-pointer focus-within:ring-2 focus-within:ring-brand"
                            >
                                <span>{{ __('Upload photo') }}</span>
                                <input
                                    id="profile-photo-input"
                                    type="file"
                                    wire:model="photo"
                                    accept="image/*"
                                    class="sr-only"
                                />
                            </label>

                            @if ($photo || $user->profile_photo_path)
                                <button
                                    type="button"
                                    wire:click="removePhoto"
                                    class="inline-flex items-center justify-center px-3 py-2 min-h-11 sm:min-h-9 rounded-lg text-ink-muted hover:text-ink hover:bg-surface-low text-sm font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                >
                                    {{ __('Remove') }}
                                </button>
                            @endif
                        </div>

                        <span class="text-xs text-ink-muted">
                            {{ __('JPG or PNG image.') }}
                        </span>

                        @error('photo')
                            <span class="text-danger dark:text-red-400 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                    <flux:input wire:model="username" :label="__('Username')" type="text" required autocomplete="username" />

                    <div>
                        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                        @if ($this->hasUnverifiedEmail)
                            <div class="mt-3">
                                <flux:text class="text-xs">
                                    {{ __('Your email address is unverified.') }}

                                    <flux:link class="text-xs cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                        {{ __('Click here to re-send the verification email.') }}
                                    </flux:link>
                                </flux:text>

                                @if (session('status') === 'verification-link-sent')
                                    <flux:text class="mt-1.5 text-xs font-medium !dark:text-green-400 !text-green-600">
                                        {{ __('A new verification link has been sent to your email address.') }}
                                    </flux:text>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div x-data="{
                        browserTimezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                        autoDetected: false,
                        init() {
                            if ('{{ auth()->user()->timezone }}' === 'UTC' && this.browserTimezone && this.browserTimezone !== 'UTC') {
                                $wire.set('timezone', this.browserTimezone);
                                this.autoDetected = true;
                            }
                        }
                    }">
                        <flux:select variant="listbox" searchable wire:model="timezone" :label="__('Timezone')" :placeholder="__('Choose timezone...')">
                            @foreach (DateTimeZone::listIdentifiers() as $tz)
                                <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <template x-if="autoDetected">
                            <flux:text class="mt-1.5 text-xs text-ink-muted">
                                {{ __('Detected from your browser') }}
                            </flux:text>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4 pt-2">
                    <x-action-message on="profile-updated" class="text-xs font-medium text-ink-muted">
                        {{ __('Saved') }}
                    </x-action-message>

                    <flux:button variant="primary" type="submit" data-test="update-profile-button" class="max-md:min-h-11">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </section>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</div>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-surface antialiased">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)]">
            <main class="flex min-w-0 flex-col bg-surface-card px-6 py-8 sm:px-12 lg:border-r lg:border-ghost lg:px-16 xl:px-20 dark:bg-surface">
                <a href="{{ route('login') }}" class="flex items-center gap-2.5 self-start" wire:navigate>
                    <x-app-logo-icon class="size-9 shrink-0 text-ink" />
                    <span class="font-manrope text-xl font-bold tracking-tight text-ink">{{ config('app.name') }}</span>
                </a>

                <div class="flex flex-1 items-center py-10 sm:py-12">
                    <div class="flex w-full max-w-md flex-col gap-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <aside class="relative hidden overflow-hidden bg-surface-low lg:flex lg:items-center lg:justify-center lg:p-10 xl:p-14 dark:bg-surface" aria-hidden="true">
                <div class="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-brand/10 blur-3xl dark:bg-brand/15"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-32 size-96 rounded-full bg-brand-glow/25 blur-3xl dark:bg-brand-glow/10"></div>

                <x-auth.board-preview />
            </aside>
        </div>
        @fluxScripts
    </body>
</html>

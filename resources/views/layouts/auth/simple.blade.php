<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-gray-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
<div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
    <div class="flex w-full max-w-sm flex-col gap-2">
        <a href="{{ route('home') }}" class="flex flex-col items-center font-medium max-w-20 m-auto" wire:navigate>

            <x-app-logo-icon/>

            <p class="text-sm text-zinc-500 font-bold">
                FunWork
            </p>
        </a>
        <div class="flex flex-col gap-6">
            {{ $slot }}
        </div>
    </div>
</div>
@fluxScripts
</body>
</html>

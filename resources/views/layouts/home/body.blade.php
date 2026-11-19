<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-surface">

<livewire:projects.home.header/>

{{ $slot }}

<flux:toast />
@fluxScripts
</body>
</html>

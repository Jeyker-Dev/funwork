<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-gray-100">

<livewire:projects.home.header/>

{{ $slot }}

@fluxScripts
</body>
</html>

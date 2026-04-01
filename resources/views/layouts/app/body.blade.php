<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-gray-100">

        <x-layouts::app.sidebar :projectId="$projectId" />

        <x-layouts::app.header :projectId="$projectId" />

        {{ $slot }}

        @fluxScripts
    </body>
</html>

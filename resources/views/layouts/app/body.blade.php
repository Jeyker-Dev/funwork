<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-gray-100">

        @if($project)
            <x-layouts::app.sidebar :project="$project" />

            <x-layouts::app.header :project="$project" />
        @endif

        {{ $slot }}

        @fluxScripts
    </body>
</html>

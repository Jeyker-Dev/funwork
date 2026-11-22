<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-surface">

        @if($project)
            <x-layouts::app.sidebar :project="$project" />

            <x-layouts::app.header :project="$project" />
        @else
            <x-layouts::app.standalone-header />
        @endif

        {{ $slot }}

        <flux:toast />
        @fluxScripts
    </body>
</html>

@php
    $elementName = 'header';
@endphp

<flux:header class="bg-white p-4">
    <x-app-logo name="FunWork"/>

    <flux:spacer/>

    <!-- Search input for desktop -->
    <livewire:projects.search-project/>

    <flux:spacer/>

    <flux:navbar class="me-4">

    </flux:navbar>

    <!-- New project button for desktop -->
    <livewire:projects.create-project :elementName="$elementName"/>

    <!-- User Dropdown content -->
    <x-desktop-user-menu/>
</flux:header>

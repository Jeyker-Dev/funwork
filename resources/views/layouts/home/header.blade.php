@php
use App\Models\Project;

    $elementName = 'header';
    $projects = Project::all();
@endphp

<flux:header class="bg-white p-4">
    <x-app-logo name="FunWork"/>

    <flux:spacer/>

    <livewire:utilities.search-input containerClass="-mb-px max-md:hidden w-6/12" inputPlaceholder="Search Projects" />

    <flux:spacer/>

    <flux:navbar class="me-4">

    </flux:navbar>

    @if(!$projects->isEmpty())
        <livewire:projects.create-project :elementName="$elementName"/>
    @endif

    <!-- User Dropdown content -->
    <x-desktop-user-menu/>
</flux:header>

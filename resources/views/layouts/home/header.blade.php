@php
    $elementName = 'header';
@endphp

<flux:header class="bg-white">
    <x-app-logo name="FunWork"/>

    <flux:spacer />

    <!-- Search input for desktop -->
    <flux:navbar class="-mb-px max-md:hidden w-6/12">
        <flux:input icon="magnifying-glass" placeholder="Search Projects" class="no-ring gray-search"  />
    </flux:navbar>

    <flux:spacer />

    <flux:navbar class="me-4">

        <!-- Mobile menu -->
        <flux:dropdown class="block md:hidden">
            <flux:navbar.item icon="cog-6-tooth" href="#" label="Search" />

            <flux:menu class="gap-4! ">
                <flux:input icon="magnifying-glass" placeholder="Search Projects" class="no-ring gray-search"  />
                <flux:menu.separator />
                <flux:menu.item icon="plus">New project</flux:menu.item>
            </flux:menu>
        </flux:dropdown>

    </flux:navbar>

    <!-- New project button for desktop -->
    <livewire:projects.create-project :elementName="$elementName"/>

    <!-- User Dropdown content -->
    <flux:dropdown position="top" align="start">
        <flux:profile avatar="https://fluxui.dev/img/demo/user.png" />
        <flux:menu>
            <flux:menu.radio.group>
                <flux:menu.radio checked>Olivia Martin</flux:menu.radio>
                <flux:menu.radio>Truly Delta</flux:menu.radio>
            </flux:menu.radio.group>
            <flux:menu.separator />
            <flux:menu.item icon="arrow-right-start-on-rectangle">Logout</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</flux:header>

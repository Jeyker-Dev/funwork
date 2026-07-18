@props([
    'breadcrumbs' => [],
    'hasItems' => false,
])

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <!-- Breadcrumbs -->
    <div class="flex items-center space-x-2 text-sm text-gray-500 font-medium">
        <button wire:click="navigateToRoot" class="hover:text-gray-900 transition-colors {{ empty($breadcrumbs) ? 'text-gray-900 font-semibold' : '' }}">Files</button>

        @if(!empty($breadcrumbs))
            <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />

            @if(count($breadcrumbs) > 3)
                <button wire:click="navigateToFolder({{ $breadcrumbs[0]->id }})" class="hover:text-gray-900 transition-colors">{{ $breadcrumbs[0]->name }}</button>
                <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                <span class="text-gray-400">...</span>
                <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                <button wire:click="navigateToFolder({{ $breadcrumbs[count($breadcrumbs)-2]->id }})" class="hover:text-gray-900 transition-colors">{{ $breadcrumbs[count($breadcrumbs)-2]->name }}</button>
                <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                <span class="text-gray-900 font-semibold">{{ $breadcrumbs[count($breadcrumbs)-1]->name }}</span>
            @else
                @foreach($breadcrumbs as $index => $crumb)
                    @if($index === count($breadcrumbs) - 1)
                        <span class="text-gray-900 font-semibold">{{ $crumb->name }}</span>
                    @else
                        <button wire:click="navigateToFolder({{ $crumb->id }})" class="hover:text-gray-900 transition-colors">{{ $crumb->name }}</button>
                        <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                    @endif
                @endforeach
            @endif
        @endif
    </div>

    <!-- Actions -->
    @if($hasItems)
        <div class="flex items-center gap-3">
            <div class="w-full md:w-64">
                <flux:input icon="magnifying-glass" placeholder="Search files..." />
            </div>
            <div class="flex gap-2 bg-white rounded-lg shadow-sm p-1 border border-gray-200">
                <button class="p-1.5 rounded-md hover:bg-gray-100 text-gray-600">
                    <flux:icon.squares-2x2 variant="solid" class="w-5 h-5" />
                </button>
                <button class="p-1.5 rounded-md hover:bg-gray-100 text-gray-400">
                    <flux:icon.list-bullet class="w-5 h-5" />
                </button>
            </div>
            <flux:modal.trigger name="create-folder">
                <flux:button icon="folder-plus" variant="outline">New Folder</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="upload-file">
                <flux:button icon="document-plus" variant="primary">New File</flux:button>
            </flux:modal.trigger>
        </div>
    @endif
</div>

@props(['file', 'isSelected' => false])

<flux:card wire:key="file-{{ $file->id }}"
    @class([
        'relative cursor-pointer transition-colors p-0 overflow-hidden flex flex-col group',
        'border-orange-500 ring-1 ring-orange-500 bg-orange-50' => $isSelected,
        'hover:border-gray-300 bg-white' => !$isSelected,
    ])>
    <div class="absolute top-2 left-2 z-30">
        <input type="checkbox" wire:model.live="selectedIds" value="file_{{ $file->id }}"
            class="rounded border-gray-300 text-orange-600 focus:ring-orange-600 cursor-pointer w-4 h-4
            {{ $isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition-opacity bg-white/80 backdrop-blur-sm">
    </div>
    <div class="absolute top-2 right-2 z-20">
        <button class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 bg-white/80 backdrop-blur-sm shadow-sm hover:bg-white transition-colors">
            <flux:icon.ellipsis-vertical class="w-5 h-5" />
        </button>
    </div>

    <!-- File Preview Area -->
    <div class="h-32 border-b border-gray-100 flex items-center justify-center relative overflow-hidden {{ $isSelected ? 'bg-orange-50' : 'bg-gray-50' }}">
        @if(str_contains($file->mime_type, 'image'))
            <div class="absolute inset-0 bg-gradient-to-tr from-orange-200/40 to-white z-0"></div>
            <flux:icon.photo class="w-12 h-12 text-orange-400 relative z-10" />
        @elseif(str_contains($file->mime_type, 'pdf'))
            <div class="text-red-500 bg-red-50 p-3 rounded-xl shadow-sm relative z-10">
                <flux:icon.document-text variant="solid" class="w-10 h-10" />
            </div>
        @else
            <div class="text-blue-500 bg-blue-50 p-3 rounded-xl shadow-sm relative z-10">
                <flux:icon.document class="w-10 h-10" />
            </div>
        @endif
    </div>

    <!-- File Metadata -->
    <div class="p-4 flex gap-3 items-start relative z-10 transition-colors {{ $isSelected ? 'bg-orange-50' : 'bg-white' }}">
        <div class="mt-1 {{ str_contains($file->mime_type, 'image') ? 'text-orange-500' : (str_contains($file->mime_type, 'pdf') ? 'text-red-500' : 'text-blue-500') }}">
            @if(str_contains($file->mime_type, 'image'))
                <flux:icon.photo class="w-5 h-5" />
            @elseif(str_contains($file->mime_type, 'pdf'))
                <flux:icon.document-text class="w-5 h-5" />
            @else
                <flux:icon.document class="w-5 h-5" />
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="font-semibold text-gray-900 truncate">{{ $file->name }}</div>
            <div class="text-xs text-gray-500 mt-1 uppercase tracking-wide">
                {{ explode('/', $file->mime_type)[1] ?? 'File' }} • {{ number_format($file->size / 1024 / 1024, 2) }} MB
            </div>
        </div>
    </div>
</flux:card>

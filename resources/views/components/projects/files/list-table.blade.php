@props([
    'folders',
    'files',
    'selectedIds' => [],
    'folderPaths' => [],
    'userTimezone' => config('app.timezone', 'UTC'),
])

@php
    use App\Enums\FileCategory;
@endphp

<section aria-label="{{ __('Files list') }}" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-low/60 text-xs font-semibold text-ink-muted">
                    <th scope="col" class="py-3 pl-4 pr-2 w-10">
                        <span class="sr-only">{{ __('Select') }}</span>
                    </th>
                    <th scope="col" class="py-3 px-4">{{ __('Name') }}</th>
                    <th scope="col" class="py-3 px-4">{{ __('Type') }}</th>
                    <th scope="col" class="py-3 px-4">{{ __('Size') }}</th>
                    <th scope="col" class="py-3 px-4">{{ __('Date') }}</th>
                    <th scope="col" class="py-3 px-4">{{ __('Folder') }}</th>
                    <th scope="col" class="py-3 pr-4 pl-2 w-12 text-right">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($folders as $folder)
                    @php
                        $isSelected = in_array('folder_'.$folder->id, $selectedIds, true);
                        $itemsCount = (int) ($folder->files_count ?? 0) + (int) ($folder->children_count ?? 0);
                        $parentPath = $folder->parent_id ? ($folderPaths[$folder->parent_id] ?? __('Root')) : __('Root');
                    @endphp
                    <tr
                        wire:key="folder-row-{{ $folder->id }}"
                        wire:click="navigateToFolder({{ $folder->id }})"
                        @class([
                            'border-t border-ghost transition-colors cursor-pointer',
                            'bg-brand-tint/40' => $isSelected,
                            'hover:bg-surface-low/60' => ! $isSelected,
                        ])
                    >
                        <td class="py-3 pl-4 pr-2" x-on:click.stop>
                            <label class="inline-flex items-center justify-center max-md:min-h-11 max-md:min-w-11 cursor-pointer" aria-label="Select {{ $folder->name }}">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedIds"
                                    value="folder_{{ $folder->id }}"
                                    @checked($isSelected)
                                    class="peer sr-only"
                                />
                                <span
                                    @class([
                                        'size-5 rounded-md border flex items-center justify-center transition-all duration-200 ease-out shadow-xs',
                                        'bg-brand-strong border-brand-strong text-white' => $isSelected,
                                        'bg-surface-card border-ghost text-transparent peer-checked:bg-brand-strong peer-checked:border-brand-strong peer-checked:text-white' => ! $isSelected,
                                    ])
                                >
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </label>
                        </td>

                        <td class="py-3 px-4 min-w-48">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="size-8 rounded-lg bg-brand-tint text-brand flex items-center justify-center shrink-0" aria-hidden="true">
                                    <flux:icon.folder variant="solid" class="size-4" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-sm font-semibold text-ink truncate" title="{{ $folder->name }}">
                                        {{ $folder->name }}
                                    </span>
                                    <span class="block text-xs text-ink-subtle tabular-nums">
                                        {{ $itemsCount }} {{ \Illuminate\Support\Str::plural('item', $itemsCount) }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted">
                            {{ __('Folder') }}
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-subtle tabular-nums">
                            —
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted tabular-nums">
                            {{ $folder->updated_at?->timezone($userTimezone)->locale('en')->format('M j, Y') ?? '—' }}
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted max-w-44 truncate" title="{{ $parentPath }}">
                            {{ $parentPath }}
                        </td>

                        <td class="py-3 pr-4 pl-2 text-right" x-on:click.stop>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="ellipsis-vertical"
                                    aria-label="More options for {{ $folder->name }}"
                                    class="text-ink-muted hover:text-ink max-md:min-h-11 max-md:min-w-11"
                                />
                                <flux:menu>
                                    <flux:menu.item wire:click="downloadFolder({{ $folder->id }})" icon="arrow-down-tray">Download</flux:menu.item>
                                    <flux:menu.item wire:click="setSingleSelectionAndMove('folder_{{ $folder->id }}')" icon="arrows-right-left">Move</flux:menu.item>
                                    <flux:menu.item x-on:click="$dispatch('open-rename-folder', { folderId: {{ $folder->id }} })" icon="pencil">Rename</flux:menu.item>
                                    <flux:menu.item wire:click="setSingleSelectionAndDelete('folder_{{ $folder->id }}')" variant="danger" icon="trash">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @endforeach

                @foreach($files as $file)
                    @php
                        $isSelected = in_array('file_'.$file->id, $selectedIds, true);
                        $category = $file->category;
                        $previewUrl = route('project.files.preview', ['project' => $file->project_id, 'projectFile' => $file->id]);
                        $fileFolderPath = $file->folder_id ? ($folderPaths[$file->folder_id] ?? __('Root')) : __('Root');
                    @endphp
                    <tr
                        wire:key="file-row-{{ $file->id }}"
                        x-on:click.prevent="$dispatch('open-lightbox', { id: {{ $file->id }} })"
                        @class([
                            'border-t border-ghost transition-colors cursor-pointer',
                            'bg-brand-tint/40' => $isSelected,
                            'hover:bg-surface-low/60' => ! $isSelected,
                        ])
                    >
                        <td class="py-3 pl-4 pr-2" x-on:click.stop>
                            <label class="inline-flex items-center justify-center max-md:min-h-11 max-md:min-w-11 cursor-pointer" aria-label="Select {{ $file->name }}">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedIds"
                                    value="file_{{ $file->id }}"
                                    @checked($isSelected)
                                    class="peer sr-only"
                                />
                                <span
                                    @class([
                                        'size-5 rounded-md border flex items-center justify-center transition-all duration-200 ease-out shadow-xs',
                                        'bg-brand-strong border-brand-strong text-white' => $isSelected,
                                        'bg-surface-card border-ghost text-transparent peer-checked:bg-brand-strong peer-checked:border-brand-strong peer-checked:text-white' => ! $isSelected,
                                    ])
                                >
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </label>
                        </td>

                        <td class="py-3 px-4 min-w-48">
                            <div class="flex items-center gap-3 min-w-0">
                                @if($category === FileCategory::Image)
                                    <div
                                        x-data="{ loaded: false, failed: false }"
                                        x-init="if ($refs.thumb?.complete) { if ($refs.thumb.naturalWidth > 0) { loaded = true; } else { failed = true; } }"
                                        class="relative size-8 rounded-lg bg-brand-tint text-brand overflow-hidden shrink-0 border border-ghost flex items-center justify-center"
                                    >
                                        <span x-show="!loaded || failed" class="flex items-center justify-center w-full h-full" aria-hidden="true">
                                            <flux:icon :icon="$category->icon()" class="size-4" />
                                        </span>
                                        <img
                                            x-ref="thumb"
                                            src="{{ $previewUrl }}"
                                            alt="{{ $file->name }}"
                                            loading="lazy"
                                            x-show="!failed"
                                            x-on:load="loaded = true"
                                            x-on:error="failed = true"
                                            x-bind:class="loaded ? 'opacity-100' : 'opacity-0'"
                                            class="absolute inset-0 z-10 w-full h-full object-cover transition-opacity duration-150"
                                        />
                                    </div>
                                @else
                                    <span @class([
                                        'size-8 rounded-lg flex items-center justify-center shrink-0',
                                        'text-red-500 bg-red-50 dark:bg-red-950/30' => $category === FileCategory::Pdf,
                                        'text-blue-500 bg-blue-50 dark:bg-blue-950/30' => $category === FileCategory::Document,
                                    ]) aria-hidden="true">
                                        <flux:icon :icon="$category->icon()" class="size-4" />
                                    </span>
                                @endif

                                <span class="text-sm font-medium text-ink truncate" title="{{ $file->name }}">
                                    {{ $file->name }}
                                </span>
                            </div>
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted">
                            {{ $file->type_label }}
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted tabular-nums">
                            {{ $file->formatted_size }}
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted tabular-nums">
                            {{ $file->updated_at?->timezone($userTimezone)->locale('en')->format('M j, Y') ?? '—' }}
                        </td>

                        <td class="py-3 px-4 whitespace-nowrap text-xs text-ink-muted max-w-44 truncate" title="{{ $fileFolderPath }}">
                            {{ $fileFolderPath }}
                        </td>

                        <td class="py-3 pr-4 pl-2 text-right" x-on:click.stop>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="ellipsis-vertical"
                                    aria-label="More options for {{ $file->name }}"
                                    class="text-ink-muted hover:text-ink max-md:min-h-11 max-md:min-w-11"
                                />
                                <flux:menu>
                                    <flux:menu.item wire:click="downloadSingleFile({{ $file->id }})" icon="arrow-down-tray">Download</flux:menu.item>
                                    <flux:menu.item wire:click="setSingleSelectionAndMove('file_{{ $file->id }}')" icon="arrows-right-left">Move</flux:menu.item>
                                    <flux:menu.item x-on:click="$dispatch('open-rename-file', { fileId: {{ $file->id }} })" icon="pencil">Rename</flux:menu.item>
                                    <flux:menu.item wire:click="setSingleSelectionAndDelete('file_{{ $file->id }}')" variant="danger" icon="trash">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<?php

use App\Enums\AreaColor;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Livewire\Forms\ProjectForm;
use App\Models\Area;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Project settings')]
class extends Component
{
    public Project $project;

    public ProjectForm $form;

    public bool $showAddAreaForm = false;

    public string $newName = '';

    public string $newColor = 'blue';

    public ?int $editingAreaId = null;

    public string $editingName = '';

    public string $editingColor = '';

    public ?int $deletingAreaId = null;

    public string $deleteConfirmationName = '';

    public string $announcement = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->form->setProject($project);
    }

    public function saveGeneral(): void
    {
        $this->authorize('update', $this->project);

        $this->form->update($this->project);
        $this->project->refresh();

        $this->dispatch('project-settings-saved');
    }

    public function selectIcon(string $icon): void
    {
        $this->authorize('update', $this->project);

        if (in_array($icon, Project::SUPPORTED_ICONS, true)) {
            $this->form->icon = $icon;
        }
    }

    public function openAddArea(): void
    {
        $this->authorize('update', $this->project);

        $this->showAddAreaForm = true;
        $this->resetErrorBag();
    }

    public function cancelAddArea(): void
    {
        $this->showAddAreaForm = false;
        $this->reset(['newName', 'newColor']);
        $this->newColor = 'blue';
        $this->resetErrorBag();
    }

    public function createArea(): void
    {
        $this->authorize('update', $this->project);

        $slug = Str::slug($this->newName);

        $validator = Validator::make(
            [
                'newName' => $this->newName,
                'slug' => $slug,
                'newColor' => $this->newColor,
            ],
            [
                'newName' => ['required', 'string', 'max:50'],
                'slug' => [
                    'required',
                    'string',
                    Rule::unique('areas', 'slug')->where('project_id', $this->project->id),
                ],
                'newColor' => ['required', Rule::in(Area::ALLOWED_COLORS)],
            ],
            [
                'slug.unique' => 'An area with this name already exists in this project.',
            ]
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->getMessageBag());
            if ($validator->errors()->has('slug')) {
                $this->addError('newName', $validator->errors()->first('slug'));
            }

            return;
        }

        $maxPosition = (int) ($this->project->areas()->max('position') ?? 0);

        $this->project->areas()->create([
            'name' => $this->newName,
            'slug' => $slug,
            'color' => $this->newColor,
            'position' => $maxPosition + 1,
        ]);

        $this->reset(['newName', 'newColor', 'showAddAreaForm']);
        $this->newColor = 'blue';
        $this->resetErrorBag();

        Flux::toast('Area created.', variant: 'success');
        $this->dispatch('areas-updated');
    }

    public function startEditing(int $id): void
    {
        $this->authorize('update', $this->project);

        $area = $this->project->areas()->findOrFail($id);
        $this->editingAreaId = $area->id;
        $this->editingName = $area->name;
        $this->editingColor = $area->color;
        $this->resetErrorBag();
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingAreaId', 'editingName', 'editingColor']);
        $this->resetErrorBag();
    }

    public function updateArea(): void
    {
        $this->authorize('update', $this->project);

        $area = $this->project->areas()->findOrFail($this->editingAreaId);
        $slug = Str::slug($this->editingName);

        $validator = Validator::make(
            [
                'editingName' => $this->editingName,
                'slug' => $slug,
                'editingColor' => $this->editingColor,
            ],
            [
                'editingName' => ['required', 'string', 'max:50'],
                'slug' => [
                    'required',
                    'string',
                    Rule::unique('areas', 'slug')->where('project_id', $this->project->id)->ignore($area->id),
                ],
                'editingColor' => ['required', Rule::in(Area::ALLOWED_COLORS)],
            ],
            [
                'slug.unique' => 'An area with this name already exists in this project.',
            ]
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->getMessageBag());
            if ($validator->errors()->has('slug')) {
                $this->addError('editingName', $validator->errors()->first('slug'));
            }

            return;
        }

        $area->update([
            'name' => $this->editingName,
            'slug' => $slug,
            'color' => $this->editingColor,
        ]);

        $this->cancelEditing();

        Flux::toast('Area updated.', variant: 'success');
        $this->dispatch('areas-updated');
    }

    public function sortArea(int $areaId, int $position): void
    {
        $this->authorize('update', $this->project);

        $areas = $this->project->areas()->orderBy('position')->orderBy('id')->get()->values();
        $currentIndex = $areas->search(fn (Area $area): bool => $area->id === $areaId);

        if ($currentIndex === false) {
            return;
        }

        $movedArea = $areas->pull($currentIndex);
        $reordered = $areas->values();
        $clampedPosition = max(0, min($position, $reordered->count()));
        $reordered->splice($clampedPosition, 0, [$movedArea]);

        foreach ($reordered->values() as $index => $area) {
            $area->update(['position' => $index + 1]);
        }

        $this->announcement = "Moved area {$movedArea->name} to position ".($clampedPosition + 1).'.';
        $this->dispatch('areas-updated');
    }

    public function moveUp(int $id): void
    {
        $this->authorize('update', $this->project);

        $areas = $this->project->areas()->orderBy('position')->orderBy('id')->get()->values();
        $index = $areas->search(fn (Area $area): bool => $area->id === $id);

        if ($index !== false && $index > 0) {
            $this->sortArea($id, $index - 1);
        }
    }

    public function moveDown(int $id): void
    {
        $this->authorize('update', $this->project);

        $areas = $this->project->areas()->orderBy('position')->orderBy('id')->get()->values();
        $index = $areas->search(fn (Area $area): bool => $area->id === $id);

        if ($index !== false && $index < $areas->count() - 1) {
            $this->sortArea($id, $index + 1);
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteArea($id);
    }

    public function confirmDeleteArea(int $id): void
    {
        $this->authorize('update', $this->project);

        $area = $this->project->areas()->findOrFail($id);
        $this->deletingAreaId = $area->id;

        Flux::modal('delete-area-confirmation')->show();
    }

    public function deleteArea(): void
    {
        $this->authorize('update', $this->project);

        if ($this->deletingAreaId !== null) {
            $area = $this->project->areas()->findOrFail($this->deletingAreaId);
            $area->delete();
            $this->deletingAreaId = null;
        }

        Flux::modal('delete-area-confirmation')->close();
        Flux::toast('Area deleted.', variant: 'success');
        $this->dispatch('areas-updated');
    }

    public function archiveProject(): void
    {
        $this->authorize('archive', $this->project);

        $this->project->archive();
        $this->project->refresh();

        Flux::modal('confirm-project-archive')->close();
        Flux::toast('Project archived.', variant: 'success');
    }

    public function unarchiveProject(): void
    {
        $this->authorize('archive', $this->project);

        $this->project->unarchive();
        $this->project->refresh();

        Flux::modal('confirm-project-unarchive')->close();
        Flux::toast('Project restored.', variant: 'success');
    }

    public function deleteProject(): void
    {
        $this->authorize('delete', $this->project);
        $this->resetErrorBag('deleteConfirmationName');

        if (trim($this->deleteConfirmationName) !== $this->project->name) {
            $this->addError('deleteConfirmationName', 'Please type the exact project name to confirm deletion.');

            return;
        }

        $this->project->delete();

        $this->redirect(route('projects'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'areas' => $this->project->areas()->withCount('tasks')->orderBy('position')->orderBy('id')->get(),
            'colors' => Area::ALLOWED_COLORS,
            'supportedIcons' => Project::SUPPORTED_ICONS,
            'projectTypes' => ProjectType::cases(),
            'projectStatuses' => [
                ProjectStatus::Planning,
                ProjectStatus::Active,
                ProjectStatus::OnHold,
                ProjectStatus::Done,
            ],
            'canManageDangerZone' => Gate::allows('archive', $this->project) && Gate::allows('delete', $this->project),
        ];
    }
};
?>

<div class="max-w-[900px] w-full mx-auto py-4 sm:py-8 px-4 sm:px-6 space-y-8">
    <div role="status" aria-live="polite" class="sr-only">
        {{ $announcement }}
    </div>

    <header>
        <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
            {{ __('Project settings') }}
        </h1>
    </header>

    <div class="flex flex-col gap-8">
        {{-- Card 1: General --}}
        <section
            id="general"
            aria-labelledby="general-heading"
            class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-7 flex flex-col gap-6"
        >
            <div>
                <h2 id="general-heading" class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('General') }}
                </h2>
            </div>

            <form wire:submit="saveGeneral" class="flex flex-col gap-6">
                {{-- Project Icon Picker --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-surface-low/60">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center bg-brand-tint rounded-xl size-13 shrink-0">
                            <flux:icon :name="$form->icon ?: 'folder'" variant="solid" class="text-brand size-6" />
                        </div>

                        <div class="flex flex-col items-start gap-0.5">
                            <span class="text-xs font-medium text-ink-muted">
                                {{ __('Project icon') }}
                            </span>

                            <flux:dropdown position="bottom" align="start">
                                <button
                                    type="button"
                                    data-test="change-icon-trigger"
                                    class="inline-flex items-center min-h-11 sm:min-h-8 text-sm font-medium text-ink hover:text-accent-content transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-lg"
                                >
                                    {{ __('Change icon') }}
                                </button>

                                <flux:menu class="p-3 w-64">
                                    <div
                                        role="radiogroup"
                                        aria-label="{{ __('Choose project icon') }}"
                                        class="grid grid-cols-4 gap-2"
                                    >
                                        @foreach ($supportedIcons as $iconName)
                                            @php
                                                $isSelectedIcon = $form->icon === $iconName;
                                            @endphp
                                            <button
                                                type="button"
                                                role="radio"
                                                aria-checked="{{ $isSelectedIcon ? 'true' : 'false' }}"
                                                aria-label="{{ Str::headline($iconName) }}"
                                                title="{{ Str::headline($iconName) }}"
                                                wire:key="project-icon-option-{{ $iconName }}"
                                                wire:click="selectIcon('{{ $iconName }}')"
                                                @class([
                                                    'flex items-center justify-center min-h-11 min-w-11 rounded-xl transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                                    'bg-brand-tint text-brand ring-2 ring-brand' => $isSelectedIcon,
                                                    'bg-surface-low text-ink-muted hover:text-ink hover:bg-surface-high' => ! $isSelectedIcon,
                                                ])
                                            >
                                                <flux:icon :name="$iconName" variant="solid" class="size-5" />
                                            </button>
                                        @endforeach
                                    </div>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                    @error('form.icon')
                        <span class="text-xs text-danger dark:text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Form Fields --}}
                <div class="flex flex-col gap-5">
                    <flux:input
                        wire:model="form.name"
                        :label="__('Name')"
                        type="text"
                        required
                    />

                    <flux:textarea
                        wire:model="form.description"
                        :label="__('Description')"
                        rows="3"
                        required
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                        <flux:select
                            variant="listbox"
                            wire:model="form.type"
                            :label="__('Type')"
                            :placeholder="__('Choose type...')"
                        >
                            @foreach ($projectTypes as $typeOption)
                                <flux:select.option :value="$typeOption->value">
                                    {{ Str::headline($typeOption->value) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select
                            variant="listbox"
                            wire:model="form.status"
                            :label="__('Status')"
                            :placeholder="__('Choose status...')"
                        >
                            @foreach ($projectStatuses as $statusOption)
                                <flux:select.option :value="$statusOption->value">
                                    {{ ProjectStatus::labelFor($statusOption->value) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input
                            wire:model="form.dueDate"
                            :label="__('Due date')"
                            type="date"
                        />
                    </div>
                </div>

                {{-- Footer --}}
                <div class="pt-2 flex items-center justify-end gap-4">
                    <x-action-message on="project-settings-saved" class="text-sm font-medium text-ink-muted">
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="check" class="size-4 text-accent-content" aria-hidden="true" />
                            <span>{{ __('Saved') }}</span>
                        </span>
                    </x-action-message>

                    <flux:button
                        variant="primary"
                        type="submit"
                        data-test="save-project-settings"
                        class="max-md:min-h-11"
                    >
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </section>

        {{-- Card 2: Areas --}}
        <section
            id="areas"
            aria-labelledby="areas-settings-heading"
            class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-7 flex flex-col gap-6 scroll-mt-20"
        >
            <div>
                <h2 id="areas-settings-heading" class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('Areas') }}
                </h2>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Organize tasks and documentation by project domain.') }}
                </p>
            </div>

            @if ($areas->isEmpty())
                <div class="rounded-xl bg-surface-low/60 px-4 py-8 text-center">
                    <p class="text-sm text-ink-muted">
                        {{ __('No areas created yet.') }}
                    </p>
                </div>
            @else
                <div
                    wire:sort="sortArea"
                    x-sort:config="{ handle: '[data-area-drag-handle]', animation: 150 }"
                    class="flex flex-col gap-1.5"
                    data-areas-list
                >
                    @foreach ($areas as $index => $area)
                        <div
                            wire:key="area-row-{{ $area->id }}"
                            wire:sort:item="{{ $area->id }}"
                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3 px-3.5 rounded-xl hover:bg-surface-low/60 transition-colors"
                        >
                            @if ($editingAreaId === $area->id)
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full">
                                    <div class="flex-1 min-w-0">
                                        <flux:input
                                            wire:model="editingName"
                                            :aria-label="__('Area name')"
                                            placeholder="Area name"
                                        />
                                    </div>

                                    <div class="w-full sm:w-40">
                                        <flux:select
                                            variant="listbox"
                                            wire:model="editingColor"
                                            :aria-label="__('Area color')"
                                        >
                                            @foreach ($colors as $colorOption)
                                                <flux:select.option :value="$colorOption">
                                                    {{ ucfirst($colorOption) }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>

                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button
                                            variant="ghost"
                                            wire:click="cancelEditing"
                                            class="max-md:min-h-11"
                                        >
                                            {{ __('Cancel') }}
                                        </flux:button>

                                        <flux:button
                                            variant="primary"
                                            wire:click="updateArea"
                                            class="max-md:min-h-11"
                                        >
                                            {{ __('Save') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <button
                                        type="button"
                                        wire:sort:handle
                                        data-area-drag-handle
                                        aria-label="{{ __('Drag to reorder :name', ['name' => $area->name]) }}"
                                        class="inline-flex items-center justify-center size-8 max-md:min-h-11 max-md:min-w-11 rounded-lg text-ink-subtle hover:text-ink cursor-grab active:cursor-grabbing shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        <flux:icon name="bars-2" class="size-4" aria-hidden="true" />
                                    </button>

                                    <span
                                        class="size-2.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($area->color) }}"
                                        aria-hidden="true"
                                    ></span>

                                    <span class="font-semibold text-sm text-ink truncate">
                                        {{ $area->name }}
                                    </span>

                                    <span class="text-sm text-ink-muted shrink-0 tabular-nums">
                                        {{ $area->tasks_count }} {{ Str::plural('task', $area->tasks_count) }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-end gap-1 shrink-0">
                                    <button
                                        type="button"
                                        wire:click="moveUp({{ $area->id }})"
                                        @disabled($index === 0)
                                        aria-label="{{ __('Move :name up', ['name' => $area->name]) }}"
                                        class="inline-flex items-center justify-center size-8 max-md:min-h-11 max-md:min-w-11 rounded-lg text-ink-muted hover:text-ink hover:bg-surface-high/60 disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        <flux:icon name="chevron-up" class="size-4" aria-hidden="true" />
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="moveDown({{ $area->id }})"
                                        @disabled($index === $areas->count() - 1)
                                        aria-label="{{ __('Move :name down', ['name' => $area->name]) }}"
                                        class="inline-flex items-center justify-center size-8 max-md:min-h-11 max-md:min-w-11 rounded-lg text-ink-muted hover:text-ink hover:bg-surface-high/60 disabled:opacity-40 disabled:pointer-events-none transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        <flux:icon name="chevron-down" class="size-4" aria-hidden="true" />
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="startEditing({{ $area->id }})"
                                        class="inline-flex items-center justify-center px-2.5 py-1.5 max-md:min-h-11 rounded-lg text-sm font-medium text-ink-muted hover:text-ink hover:bg-surface-high/60 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        {{ __('Edit') }}
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="confirmDeleteArea({{ $area->id }})"
                                        aria-label="{{ __('Delete :name', ['name' => $area->name]) }}"
                                        class="inline-flex items-center justify-center size-8 max-md:min-h-11 max-md:min-w-11 rounded-lg text-ink-muted hover:text-danger dark:hover:text-red-400 hover:bg-danger-tint dark:hover:bg-red-950/30 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger"
                                    >
                                        <flux:icon name="trash" class="size-4" aria-hidden="true" />
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($showAddAreaForm)
                <form wire:submit="createArea" class="rounded-xl bg-surface-low/60 p-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                        <div class="sm:col-span-2">
                            <flux:input
                                wire:model="newName"
                                :label="__('Name')"
                                placeholder="e.g. Infrastructure"
                            />
                        </div>

                        <div>
                            <flux:select
                                variant="listbox"
                                wire:model="newColor"
                                :label="__('Color')"
                            >
                                @foreach ($colors as $colorOption)
                                    <flux:select.option :value="$colorOption">
                                        {{ ucfirst($colorOption) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <flux:button
                            variant="ghost"
                            type="button"
                            wire:click="cancelAddArea"
                            class="max-md:min-h-11"
                        >
                            {{ __('Cancel') }}
                        </flux:button>

                        <flux:button
                            variant="primary"
                            type="submit"
                            class="max-md:min-h-11"
                        >
                            {{ __('Add area') }}
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="pt-1">
                    <button
                        type="button"
                        wire:click="openAddArea"
                        data-test="open-add-area"
                        class="inline-flex items-center gap-2 bg-surface-low hover:bg-surface-high text-ink px-4 py-2 min-h-11 sm:min-h-10 rounded-xl text-sm font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <flux:icon name="plus" class="size-4" aria-hidden="true" />
                        <span>{{ __('Add area') }}</span>
                    </button>
                </div>
            @endif
        </section>

        {{-- Card 3: Repositories --}}
        <livewire:projects.repository-connections :project="$project" />

        {{-- Card 4: Danger Zone --}}
        @if ($canManageDangerZone)
            <section
                id="danger-zone"
                aria-labelledby="danger-zone-heading"
                class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-7 flex flex-col gap-6"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 text-danger dark:text-red-400 shrink-0" aria-hidden="true" />
                    <h2 id="danger-zone-heading" class="font-headline font-bold text-xl text-danger dark:text-red-400 tracking-tight">
                        {{ __('Danger zone') }}
                    </h2>
                </div>

                <div class="flex flex-col gap-4">
                    {{-- Row 1: Archive / Unarchive --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-surface-low/60">
                        <div class="flex flex-col gap-0.5">
                            @if ($project->isArchived())
                                <span class="font-semibold text-sm text-ink">
                                    {{ __('Unarchive project') }}
                                </span>
                                <span class="text-sm text-ink-muted">
                                    {{ __('Restore this project to your active projects list.') }}
                                </span>
                            @else
                                <span class="font-semibold text-sm text-ink">
                                    {{ __('Archive project') }}
                                </span>
                                <span class="text-sm text-ink-muted">
                                    {{ __('Hide it from your projects list. You can restore it later.') }}
                                </span>
                            @endif
                        </div>

                        <div class="shrink-0">
                            @if ($project->isArchived())
                                <flux:modal.trigger name="confirm-project-unarchive">
                                    <button
                                        type="button"
                                        data-test="unarchive-project-trigger"
                                        class="inline-flex items-center justify-center px-4 py-2 min-h-11 sm:min-h-10 rounded-xl bg-surface-card border border-ghost hover:bg-surface-high text-ink text-sm font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        {{ __('Unarchive') }}
                                    </button>
                                </flux:modal.trigger>
                            @else
                                <flux:modal.trigger name="confirm-project-archive">
                                    <button
                                        type="button"
                                        data-test="archive-project-trigger"
                                        class="inline-flex items-center justify-center px-4 py-2 min-h-11 sm:min-h-10 rounded-xl bg-surface-card border border-ghost hover:bg-surface-high text-ink text-sm font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        {{ __('Archive') }}
                                    </button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                    </div>

                    {{-- Row 2: Delete --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-danger-tint/60 dark:bg-red-950/20">
                        <div class="flex flex-col gap-0.5">
                            <span class="font-semibold text-sm text-ink">
                                {{ __('Delete project') }}
                            </span>
                            <span class="text-sm text-ink-muted">
                                {{ __('Permanently delete the project, its tasks, notebooks and files.') }}
                            </span>
                        </div>

                        <div class="shrink-0">
                            <flux:modal.trigger name="confirm-project-deletion">
                                <button
                                    type="button"
                                    data-test="delete-project-trigger"
                                    class="inline-flex items-center justify-center px-4 py-2 min-h-11 sm:min-h-10 rounded-xl bg-surface-card border border-ghost hover:bg-danger-tint dark:hover:bg-red-950/40 text-danger dark:text-red-400 text-sm font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger"
                                >
                                    {{ __('Delete project') }}
                                </button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </div>

    {{-- Delete Area Confirmation Modal --}}
    <flux:modal name="delete-area-confirmation" class="w-[calc(100vw-2rem)] max-w-md space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete area') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Are you sure you want to delete this area? Tasks and notebooks in this area will remain without an area.') }}
            </flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="max-md:min-h-11">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="deleteArea" class="max-md:min-h-11">
                {{ __('Delete area') }}
            </flux:button>
        </div>
    </flux:modal>

    @if ($canManageDangerZone)
        {{-- Archive Project Confirmation Modal --}}
        <flux:modal name="confirm-project-archive" class="w-[calc(100vw-2rem)] max-w-md space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Archive project?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('This will hide :name from your active projects list. You can unarchive it at any time from project settings.', ['name' => $project->name]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="max-md:min-h-11">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" wire:click="archiveProject" data-test="confirm-archive-project" class="max-md:min-h-11">
                    {{ __('Archive project') }}
                </flux:button>
            </div>
        </flux:modal>

        {{-- Unarchive Project Confirmation Modal --}}
        <flux:modal name="confirm-project-unarchive" class="w-[calc(100vw-2rem)] max-w-md space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Unarchive project?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('This will restore :name to your active projects list.', ['name' => $project->name]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="max-md:min-h-11">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" wire:click="unarchiveProject" data-test="confirm-unarchive-project" class="max-md:min-h-11">
                    {{ __('Unarchive project') }}
                </flux:button>
            </div>
        </flux:modal>

        {{-- Delete Project Confirmation Modal --}}
        <flux:modal name="confirm-project-deletion" :show="$errors->has('deleteConfirmationName')" focusable class="w-[calc(100vw-2rem)] max-w-lg">
            <form wire:submit="deleteProject" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete project') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('This action cannot be undone. It will permanently delete :name and all of its tasks, notebooks, decisions, and files. Please type :name to confirm.', ['name' => $project->name]) }}
                    </flux:text>
                </div>

                <flux:input
                    wire:model="deleteConfirmationName"
                    :label="__('Project name')"
                    :placeholder="$project->name"
                    type="text"
                    required
                />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="max-md:min-h-11">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="danger"
                        type="submit"
                        data-test="confirm-delete-project"
                        class="max-md:min-h-11"
                    >
                        {{ __('Delete project') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>

<?php

use App\Models\Area;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $newName = '';
    public string $newColor = 'blue';

    public ?int $editingAreaId = null;
    public string $editingName = '';
    public string $editingColor = '';

    public ?int $deletingAreaId = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function with(): array
    {
        return [
            'areas' => $this->project->areas()->get(),
            'colors' => Area::ALLOWED_COLORS,
        ];
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

        $this->reset(['newName', 'newColor']);
        $this->newColor = 'blue';
        $this->resetErrorBag();

        Flux::toast('Area created successfully.', variant: 'success');
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

        Flux::toast('Area updated successfully.', variant: 'success');
        $this->dispatch('areas-updated');
    }

    public function moveUp(int $id): void
    {
        $this->authorize('update', $this->project);

        $areas = $this->project->areas()->orderBy('position')->orderBy('id')->get()->values();
        $index = $areas->search(fn ($a) => $a->id === $id);

        if ($index !== false && $index > 0) {
            $current = $areas[$index];
            $prev = $areas[$index - 1];

            $areas[$index] = $prev;
            $areas[$index - 1] = $current;

            foreach ($areas as $pos => $area) {
                $area->update(['position' => $pos + 1]);
            }

            $this->dispatch('areas-updated');
        }
    }

    public function moveDown(int $id): void
    {
        $this->authorize('update', $this->project);

        $areas = $this->project->areas()->orderBy('position')->orderBy('id')->get()->values();
        $index = $areas->search(fn ($a) => $a->id === $id);

        if ($index !== false && $index < $areas->count() - 1) {
            $current = $areas[$index];
            $next = $areas[$index + 1];

            $areas[$index] = $next;
            $areas[$index + 1] = $current;

            foreach ($areas as $pos => $area) {
                $area->update(['position' => $pos + 1]);
            }

            $this->dispatch('areas-updated');
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('update', $this->project);

        $area = $this->project->areas()->findOrFail($id);
        $this->deletingAreaId = $area->id;

        Flux::modal('delete-area-confirmation')->show();
    }

    public function deleteArea(): void
    {
        $this->authorize('update', $this->project);

        if ($this->deletingAreaId) {
            $area = $this->project->areas()->findOrFail($this->deletingAreaId);
            $area->delete();
            $this->deletingAreaId = null;
        }

        Flux::modal('delete-area-confirmation')->close();
        Flux::toast('Area deleted successfully.', variant: 'success');
        $this->dispatch('areas-updated');
    }
};
?>

<div>
    <flux:modal.trigger name="manage-areas-modal">
        <flux:button icon="tag" variant="ghost">Areas</flux:button>
    </flux:modal.trigger>

    <flux:modal name="manage-areas-modal" class="w-xl space-y-6">
        <div>
            <flux:heading size="lg">Project Areas</flux:heading>
            <flux:text class="mt-1">
                Configure areas to label and filter tasks and notebooks in this project.
            </flux:text>
        </div>

        <div class="space-y-3">
            @forelse($areas as $index => $area)
                <div wire:key="area-item-{{ $area->id }}" class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
                    @if($editingAreaId === $area->id)
                        <div class="flex flex-col sm:flex-row items-center gap-2 w-full">
                            <div class="flex-1 w-full">
                                <flux:input size="sm" placeholder="Area name" wire:model="editingName" />
                            </div>
                            <div class="w-full sm:w-32">
                                <flux:select size="sm" variant="listbox" wire:model="editingColor">
                                    @foreach($colors as $colorOption)
                                        <flux:select.option :value="$colorOption">{{ ucfirst($colorOption) }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button size="sm" variant="primary" wire:click="updateArea">Save</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelEditing">Cancel</flux:button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <flux:badge size="sm" :color="$area->color">{{ $area->name }}</flux:badge>
                            <span class="text-xs text-zinc-400 font-mono">({{ $area->slug }})</span>
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="arrow-up"
                                wire:click="moveUp({{ $area->id }})"
                                :disabled="$index === 0"
                                aria-label="Move up"
                            />
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="arrow-down"
                                wire:click="moveDown({{ $area->id }})"
                                :disabled="$index === $areas->count() - 1"
                                aria-label="Move down"
                            />
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="pencil-square"
                                wire:click="startEditing({{ $area->id }})"
                                aria-label="Edit area"
                            />
                            <flux:button
                                size="xs"
                                variant="danger"
                                icon="trash"
                                wire:click="confirmDelete({{ $area->id }})"
                                aria-label="Delete area"
                            />
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-4 text-zinc-500 text-sm">
                    No areas created yet.
                </div>
            @endforelse
        </div>

        <flux:separator />

        <div class="space-y-4">
            <flux:heading size="sm">Create New Area</flux:heading>
            <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <div class="flex-1 w-full">
                    <flux:input label="Name" placeholder="e.g. Infrastructure" wire:model="newName" />
                </div>
                <div class="w-full sm:w-36">
                    <flux:select label="Color" variant="listbox" wire:model="newColor">
                        @foreach($colors as $colorOption)
                            <flux:select.option :value="$colorOption">{{ ucfirst($colorOption) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button variant="primary" wire:click="createArea">Add Area</flux:button>
            </div>
            @error('newName')
                <div class="text-xs text-red-500">{{ $message }}</div>
            @enderror
            @error('newColor')
                <div class="text-xs text-red-500">{{ $message }}</div>
            @enderror
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-area-confirmation" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Delete Area</flux:heading>
            <flux:text class="mt-2">
                Are you sure you want to delete this area? Tasks and notebooks of this area will remain without an area.
            </flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="deleteArea">Delete</flux:button>
        </div>
    </flux:modal>
</div>
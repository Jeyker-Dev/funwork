<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Livewire\Forms\TaskForm;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public TaskForm $form;

    public Project $project;

    public ?int $projectId = null;

    public ?string $status = null;

    public bool $hideTrigger = false;

    public function mount(Project $project, ?string $status = null, bool $hideTrigger = false): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
        $this->status = $status;
        $this->hideTrigger = $hideTrigger;

        $this->form->status = $status ?? TaskStatus::Todo->value;
        $this->form->priority = TaskPriority::Low->value;
    }

    #[On('open-create-task')]
    public function openForStatus(string $status = TaskStatus::Todo->value): void
    {
        $this->authorize('create', [Task::class, $this->project]);

        $resolvedStatus = TaskStatus::tryFrom($status)?->value ?? TaskStatus::Todo->value;

        $this->resetValidation();
        $this->status = $resolvedStatus;
        $this->form->status = $resolvedStatus;
        $this->form->priority = $this->form->priority ?: TaskPriority::Low->value;

        Flux::modal($this->modalName())->show();
    }

    public function modalName(): string
    {
        $suffix = $this->hideTrigger ? 'board' : ($this->status ?? 'all');

        return 'create-task-'.$this->projectId.'-'.$suffix;
    }

    public function with(): array
    {
        return [
            'user' => auth()->user(),
            'modalName' => $this->modalName(),
        ];
    }

    public function save(): void
    {
        $this->authorize('create', [Task::class, $this->project]);

        $this->form->projectId = $this->projectId;
        $this->form->status = $this->form->status ?: ($this->status ?? TaskStatus::Todo->value);

        $maxPosition = Task::where('project_id', $this->projectId)
            ->where('status', $this->form->status)
            ->max('position');

        $this->form->position = $maxPosition ? ((int) $maxPosition) + 1 : 1;

        $this->form->store();

        $this->form->status = $this->status ?? TaskStatus::Todo->value;
        $this->form->priority = TaskPriority::Low->value;

        $this->dispatch('task-created');
    }
};
?>

<div>
    @if(! $hideTrigger)
        <flux:modal.trigger name="{{ $modalName }}">
            <flux:button size="sm" variant="ghost" icon="plus" class="w-full justify-start text-ink-muted hover:text-ink max-md:min-h-11">
                Add task
            </flux:button>
        </flux:modal.trigger>
    @endif

    <flux:modal name="{{ $modalName }}" class="w-[calc(100vw-2rem)] max-w-xl">
        <form wire:submit.prevent="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Create Task</flux:heading>
                <flux:text class="mt-1.5 text-ink-muted">
                    Add a new task to {{ $project->name }}.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Title</flux:label>
                <flux:input placeholder="Write the onboarding notebook" wire:model="form.title" />
                <flux:error name="form.title" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea rows="4" placeholder="Add acceptance criteria or markdown checklist (- [ ] ...)" wire:model="form.description" />
                <flux:error name="form.description" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select variant="listbox" placeholder="Choose status..." wire:model="form.status">
                        @foreach(TaskStatus::cases() as $taskStatus)
                            <flux:select.option value="{{ $taskStatus->value }}">{{ $taskStatus->value }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.status" />
                </flux:field>

                <flux:field>
                    <flux:label>Priority</flux:label>
                    <flux:select variant="listbox" placeholder="Choose priority..." wire:model="form.priority">
                        @foreach(TaskPriority::cases() as $taskPriority)
                            <flux:select.option value="{{ $taskPriority->value }}">{{ $taskPriority->value }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.priority" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Area</flux:label>
                    <flux:select variant="listbox" placeholder="No area" wire:model="form.areaId">
                        <flux:select.option :value="null">No area</flux:select.option>
                        @foreach($project->areas as $area)
                            <flux:select.option :value="$area->id">{{ $area->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.areaId" />
                </flux:field>

                <flux:field>
                    <flux:label>Due date</flux:label>
                    <flux:input type="date" wire:model="form.dueDate" />
                    <flux:error name="form.dueDate" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Assigned Users</flux:label>
                <flux:select variant="listbox" multiple placeholder="Choose users..." wire:model="form.assignedUserIds">
                    <flux:select.option value="{{ $user->id }}">{{ $user->display_name }}</flux:select.option>
                </flux:select>
                <flux:error name="form.assignedUserIds" />
            </flux:field>

            <div class="flex items-center justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="max-md:min-h-11">Cancel</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" class="max-md:min-h-11">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

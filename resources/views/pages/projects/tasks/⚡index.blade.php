<?php

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app', ['title' => 'Tasks'])]
#[Title('Tasks')]
class extends Component {
    public ?Project $project = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col">
    <livewire:projects.tasks.task-board :project="$project" />
</div>

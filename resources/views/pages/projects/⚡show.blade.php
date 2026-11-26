<?php

use App\Actions\Projects\GetProjectHomeDataAction;
use App\Enums\TaskStatus;
use App\Livewire\Forms\QuickCaptureForm;
use App\Models\Project;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app', ['title' => 'Home'])]
class extends Component
{
    public Project $project;

    public QuickCaptureForm $captureForm;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->captureForm->setProject($project);
    }

    public function saveQuickCapture(): void
    {
        $this->authorize('view', $this->project);

        $this->captureForm->save();
        Flux::toast('Item captured to Inbox');
    }

    public function toggleTaskCompletion(int $taskId): void
    {
        $task = $this->project->tasks()->findOrFail($taskId);
        $this->authorize('update', $task);

        $nextStatus = $task->status === TaskStatus::Done
            ? TaskStatus::Todo
            : TaskStatus::Done;

        $task->changeStatus($nextStatus);
    }

    #[On('areas-updated')]
    #[On('task-created')]
    #[On('file-uploaded')]
    public function refreshHome(): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function with(GetProjectHomeDataAction $getProjectHomeData): array
    {
        $home = $getProjectHomeData($this->project, auth()->user());

        return [
            'home' => $home,
            'stats' => $home->stats,
            'health' => $home->health,
            'todayTasks' => $home->todayTasks,
            'upcomingTasks' => $home->upcomingTasks,
            'areas' => $home->areas,
            'recentActivities' => $home->recentActivities,
        ];
    }
};
?>

<div class="max-w-7xl mx-auto w-full py-2 sm:py-4 space-y-6 sm:space-y-8">
    <x-projects.dashboard.project-header :project="$project" :stats="$stats" />

    <x-projects.dashboard.project-health :health="$health" />

    <x-projects.dashboard.quick-capture :project="$project" />

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-stretch">
        <div class="lg:col-span-7 flex flex-col">
            <x-projects.dashboard.today-tasks
                :tasks="$todayTasks"
                :upcoming-tasks="$upcomingTasks"
                :project="$project"
                :home="$home"
                :local-today="$home->localToday"
            />
        </div>

        <div class="lg:col-span-5 flex flex-col gap-6 sm:gap-8">
            <x-projects.dashboard.areas-progress :areas="$areas" :project="$project" />

            <x-projects.dashboard.recent-activity :activities="$recentActivities" :project="$project" />
        </div>
    </div>
</div>

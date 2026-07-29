<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\QuickCaptureForm;
use Flux\Flux;

new #[Layout('layouts.app', ['title' => 'Home', 'project' => '$project'])]
class extends Component {
    public Project $project;
    
    public QuickCaptureForm $captureForm;

    public function mount(Project $project)
    {
        $this->project = $project;
        $this->captureForm->setProject($project);
    }
    
    public function saveQuickCapture()
    {
        $this->captureForm->save();
        Flux::toast('Item captured to Inbox');
    }

    public function with(): array
    {
        return [
            'recentActivities' => $this->project->getRecentActivities()->take(5),
            'urgentTasks' => $this->project->tasks()
                ->where('due_at', '<=', now())
                ->where('status', '!=', 'completed')
                ->orderBy('due_at', 'asc')
                ->take(5)
                ->get(),
            'stats' => [
                'tasks_count' => $this->project->tasks()->where('status', '!=', 'completed')->count(),
                'files_count' => $this->project->files()->count(),
                'notebooks_count' => $this->project->notebooks()->count(),
            ]
        ];
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <x-projects.dashboard.stats-cards :stats="$stats" />
        <x-projects.dashboard.quick-capture :project="$project" />
        <x-projects.dashboard.urgent-tasks :tasks="$urgentTasks" :project="$project" />
    </div>
    <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <x-projects.dashboard.recent-activity :activities="$recentActivities" :project="$project" />
    </div>
</div>

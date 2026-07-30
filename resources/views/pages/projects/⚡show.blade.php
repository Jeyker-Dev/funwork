<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\QuickCaptureForm;
use Flux\Flux;

new #[Layout('layouts.app', ['title' => 'Home'])]
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
                ->where('due_date', '<=', now())
                ->where('status', '!=', 'completed')
                ->orderBy('due_date', 'asc')
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-8">
    <div class="grid grid-cols-12 gap-6">
        <!-- Left Column -->
        <div class="col-span-12 lg:col-span-7 flex flex-col gap-6">
            <x-projects.dashboard.quick-capture :project="$project" />
            <x-projects.dashboard.stats-cards :stats="$stats" />
            <x-projects.dashboard.urgent-tasks :tasks="$urgentTasks" :project="$project" />
        </div>
        
        <!-- Right Column -->
        <div class="col-span-12 lg:col-span-5 flex flex-col gap-6 h-full">
            <x-projects.dashboard.recent-activity :activities="$recentActivities" :project="$project" />
        </div>
    </div>
</div>

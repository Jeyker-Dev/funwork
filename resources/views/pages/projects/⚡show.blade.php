<?php

use App\Actions\Projects\SyncProjectPackToGoogleDocAction;
use App\Enums\TaskStatus;
use App\Services\Google\GoogleAuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use App\Livewire\Forms\QuickCaptureForm;
use App\Models\Project;
use App\Support\DateHelper;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app', ['title' => 'Home'])]
class extends Component {
    public Project $project;
    
    public QuickCaptureForm $captureForm;

    public function mount(Project $project)
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->captureForm->setProject($project);
    }
    
    public function saveQuickCapture()
    {
        $this->authorize('view', $this->project);

        $this->captureForm->save();
        Flux::toast('Item captured to Inbox');
    }

    public function syncGoogleDoc(SyncProjectPackToGoogleDocAction $syncPack)
    {
        try {
            $syncPack->execute($this->project, auth()->user());
        } catch (GoogleAuthorizationException) {
            session()->put('google_return_to', route('projects.show', $this->project, absolute: false));

            return $this->redirectRoute('google.redirect');
        } catch (RequestException|ConnectionException $e) {
            report($e);
            Flux::toast('No se pudo sincronizar con Google Docs. Intentá de nuevo.', variant: 'danger');

            return null;
        }

        $this->project->refresh();
        Flux::toast('Project pack sincronizado con Google Docs.', variant: 'success');

        return null;
    }

    public function with(): array
    {
        $localToday = DateHelper::userToday();

        return [
            'recentActivities' => $this->project->getRecentActivities()->take(5),
            'urgentTasks' => $this->project->tasks()
                ->whereDate('due_date', '<=', $localToday)
                ->where('status', '!=', TaskStatus::Done->value)
                ->orderBy('due_date', 'asc')
                ->take(5)
                ->get(),
            'stats' => [
                'tasks_count' => $this->project->tasks()->where('status', '!=', TaskStatus::Done->value)->count(),
                'today_tasks_count' => $this->project->tasks()->where('status', '!=', TaskStatus::Done->value)->whereDate('due_date', $localToday)->count(),
                'inbox_count' => \App\Models\InboxItem::where('project_id', $this->project->id)->count(),
            ],
        ];
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-8">
    
    <!-- Greeting & Top Metrics -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-orange-500 break-words">¡Buenos días, {{ auth()->user()->display_name ?? 'Alex' }}!</h1>
        <p class="text-gray-500 dark:text-zinc-400 mt-1">Aquí tienes un resumen de tu espacio de trabajo.</p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <flux:button :href="route('project.pack', $project)" icon="arrow-down-tray" size="sm">
                Descargar Project pack
            </flux:button>

            <flux:button wire:click="syncGoogleDoc" icon="arrow-path" size="sm">
                Sincronizar con Google Docs
            </flux:button>

            @if ($project->google_doc_id)
                <flux:link :href="'https://docs.google.com/document/d/'.$project->google_doc_id.'/edit'" target="_blank" class="text-sm">
                    Abrir Google Doc
                </flux:link>

                @if ($project->google_doc_synced_at)
                    <flux:text class="text-xs">
                        Última sincronización: {{ $project->google_doc_synced_at->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('Y-m-d H:i') }}
                    </flux:text>
                @endif
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4 sm:max-w-md my-6">
            <!-- Metric 1: Tareas -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl p-4 border border-gray-100 dark:border-zinc-800 shadow-sm flex items-center gap-4">
                <div class="flex items-center justify-center bg-orange-50 dark:bg-orange-900/20 text-orange-500 rounded-full w-10 h-10 shrink-0">
                    <flux:icon name="check-circle" variant="solid" class="w-6 h-6" />
                </div>
                <div class="flex flex-col">
                    <span class="text-2xl font-bold text-neutral-900 dark:text-white leading-none">{{ $stats['today_tasks_count'] > 0 ? $stats['today_tasks_count'] : $stats['tasks_count'] }}</span>
                    <span class="text-sm text-gray-500 dark:text-zinc-400 font-medium">Tareas para hoy</span>
                </div>
            </div>

            <!-- Metric 2: Inbox -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl p-4 border border-gray-100 dark:border-zinc-800 shadow-sm flex items-center gap-4">
                <div class="flex items-center justify-center bg-amber-50 dark:bg-amber-900/20 text-amber-500 rounded-full w-10 h-10 shrink-0">
                    <flux:icon name="light-bulb" variant="solid" class="w-6 h-6" />
                </div>
                <div class="flex flex-col">
                    <span class="text-2xl font-bold text-neutral-900 dark:text-white leading-none">{{ $stats['inbox_count'] }}</span>
                    <span class="text-sm text-gray-500 dark:text-zinc-400 font-medium">Ideas en Inbox</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <!-- Left Column -->
        <div class="col-span-12 lg:col-span-7 flex flex-col gap-6">
            <x-projects.dashboard.quick-capture :project="$project" />
            <x-projects.dashboard.urgent-tasks :tasks="$urgentTasks" :project="$project" />
        </div>
        
        <!-- Right Column -->
        <div class="col-span-12 lg:col-span-5 flex flex-col gap-6 h-full">
            <x-projects.dashboard.recent-activity :activities="$recentActivities" :project="$project" />
        </div>
    </div>
</div>

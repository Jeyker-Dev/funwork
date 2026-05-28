<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.app')]
class extends Component {
    public Project $project;
    public int $projectId;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->projectId = $project->id;
    }

    public function with(): array
    {
        return [
            'project' => $this->project,
            'notebooks' => $this->project->notebooks,
        ];
    }

    public function render()
    {
        return view('pages.projects.notebooks.⚡index')
            ->layout('layouts.app', [
                'title' => 'Notebooks',
                'project' => $this->project,
            ]);
    }

    #[On('created-notebook')]
    public function refreshNotebooks(): void
    {
        $this->with();
    }
};
?>

<div>
    @if($notebooks->isEmpty())
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div>
                <h1 class="text-xl md:text-2xl font-bold mb-4">
                    Notebooks
                </h1>

                <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                    Manage and track your project notebooks with your team in one place.
                </p>
            </div>

            <div class="flex items-center justify-center flex-col gap-4 mt-8">
                <flux:icon.book-open class="w-32 h-32 text-gray-400"/>

                <div class="text-center">
                    <flux:heading size="xl" class="mt-4 mb-2">No notebooks yet</flux:heading>

                    <flux:text>
                        Create your first notebook to start capturing your project’s ideas and information.
                    </flux:text>
                </div>

                <livewire:projects.notebooks.create-notebook :project="$project"/>

                <div
                    class="w-3/12 border border-gray-200 rounded-lg p-4 mt-4 bg-white flex gap-2 items-center justify-center">
                    <flux:icon.light-bulb variant="solid" class="size-5 text-orange-500"/>

                    <flux:text class="text-gray-500 text-xs mt-2 font-bold">
                        Notebooks allow you to group and structure important information for your project.
                    </flux:text>
                </div>
            </div>
        </div>
    @else
        <div class="mb-12">
            <div class="mb-8">
                <h1 class="text-xl md:text-4xl lg:text-5xl font-bold mb-4">
                    Your Digital<br>
                    <span class="text-orange-500 mt-4 block">Thoughts, Curated.</span>
                </h1>

                <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                    Organize your creative vision with precision and style. Every insight matters in your journey.
                </p>
            </div>


            <livewire:projects.notebooks.create-notebook :project="$project"/>
        </div>

        <div>
            <flux:heading size="xl" class="mt-4 mb-2">All Notebook</flux:heading>

            <flux:separator class="mt-6 mb-8"/>

            <livewire:projects.notebooks.show-notebooks :project="$project"/>
        </div>
    @endif
</div>

<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;

new #[Layout('layouts.app')]
#[Title('Notebooks')]
class extends Component {
    public ?Project $project = null;
    public ?int $projectId = null;

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
            <x-title-text
                title="Notebooks"
                description="Manage and track your project notebooks with your team in one place."
                class="mb-0"
            />

            <div class="flex items-center justify-center flex-col gap-4 mt-8">
                <flux:icon.book-open class="w-32 h-32 text-gray-400"/>

                <x-title-text
                    title="No Notebooks,"
                    highlight="Yet."
                    description="Create your first notebook to start capturing your project’s ideas and information."
                    class="text-center flex flex-col items-center mb-0"
                />

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
            <x-title-text
                title="Your Digital"
                highlight="Thoughts, Curated."
                description="Organize your creative vision with precision and style. Every insight matters in your journey."
            />


            <livewire:projects.notebooks.create-notebook :project="$project"/>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <flux:heading size="xl" class="mt-4 mb-2">All Notebook</flux:heading>

                <livewire:projects.notebooks.filters/>
            </div>

            <flux:separator class="mt-6 mb-8"/>

            <livewire:projects.notebooks.show-notebooks :project="$project"/>
        </div>
    @endif
</div>

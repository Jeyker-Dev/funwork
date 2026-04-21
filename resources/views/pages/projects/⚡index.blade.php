<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.home', ['title' => 'Projects'])]
class extends Component {
    public function with(): array
    {
        return [
            'projects' => Project::all(),
        ];
    }

    #[On('project-created')]
    public function refreshProjects(): void
    {
        $this->with();
    }
};
?>

<div>
    <flux:main class="bg-gray-100">

        <h1 class="text-xl md:text-2xl font-bold mb-4">Projects</h1>

        @if($projects->isEmpty())
            <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                Here you can manage all your projects. Create new projects, view existing ones, and collaborate with
                your team.
            </p>
        @else
            <p class="text-gray-500 font-normal text-sm md:text-base">
                Pick a project to continue working on it or create a new one to get started.
            </p>
        @endif

        @if($projects->isEmpty())
            <livewire:projects.create-project elementName=""/>
        @else
            <livewire:projects.show-projects/>
        @endif
    </flux:main>
</div>

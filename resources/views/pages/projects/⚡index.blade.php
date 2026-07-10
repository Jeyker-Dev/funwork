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
            'projects' => Project::where('owner_id', auth()->id())->get(),
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

        <x-title-text 
            title="All Your Projects," 
            highlight="Centralized Workspace."
        >
            <x-slot:description>
                @if($projects->isEmpty())
                    Here you can manage all your projects. Create new projects, view existing ones, and collaborate with your team.
                @else
                    Pick a project to continue working on it or create a new one to get started.
                @endif
            </x-slot:description>
        </x-title-text>

        @if($projects->isEmpty())
            <livewire:projects.create-project elementName=""/>
        @else
            <livewire:projects.show-projects/>
        @endif
    </flux:main>
</div>

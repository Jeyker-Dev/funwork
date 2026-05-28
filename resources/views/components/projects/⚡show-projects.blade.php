<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component {
    public string $search = '';

    public function with(): array
    {
        return [
            'projects' => Project::where('name', 'like', '%' . $this->search . '%')->get(),
        ];
    }

    #[On('project-created')]
    public function refreshProjects(): void
    {
        $this->with();
    }

    #[On('search-updated')]
    public function syncSearch(string $search): void
    {
        $this->search = $search;
    }
};
?>

<div
    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 lg:gap-10 xl:gap-4 2xl:gap-6">
    @foreach($projects as $project)
        <a href="{{ route('projects.show', ['project' => $project]) }}" class="block">
            <flux:card
                class="space-y-6 mt-10 line-card relative overflow-hidden transition-all delay-100 duration-300 ease-in-out hover:shadow-lg hover:translate-y-2 group">
                <div class="flex items-center justify-between w-full">
                    <x-projects.icon-project :project="$project"/>

                    <flux:badge class="text-xs! px-4! sm:text-sm!" color="orange">
                        {{ $project->type }}
                    </flux:badge>
                </div>

                <div>
                    <flux:heading size="lg"
                                  class="group-hover:text-orange-500 font-bold!">{{ $project->name }}</flux:heading>
                    <flux:text
                        class="mt-2 leading-normal line-clamp-8 md:line-clamp-10 lg:line-clamp-12">{{ $project->description }}</flux:text>
                </div>

                <flux:spacer class="bg-gray-100 h-0.5"/>

                <div>
                    <div class="flex flex-col items-start justify-start gap-3">
                        <div class="mt-6 flex items-center gap-4">
                            <flux:avatar size="lg" src="https://unavatar.io/x/taylorotwell"/>

                            <div>
                                <flux:heading size="lg">{{ $project->owner->name }}</flux:heading>
                                <flux:text>Creator of Laravel</flux:text>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full gap-2">
                            <div class="flex items-center gap-2 my-2">
                                <flux:icon.bolt variant="solid" class="text-green-500 size-5"/>

                                <flux:text class="text-green-500 font-normal">
                                    {{ $project->status }}
                                </flux:text>
                            </div>

                            <div>
                                <div class="flex items-center gap-2 my-2">
                                    <flux:icon.calendar variant="solid" class="text-gray-500 size-5"/>

                                    <flux:text class="text-gray-500 font-normal">
                                        <span
                                            class="text-orange-500 font-bold">Due:</span> {{ \Carbon\Carbon::parse($project->due_at)->format('Y-m-d') }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>
        </a>
    @endforeach
</div>

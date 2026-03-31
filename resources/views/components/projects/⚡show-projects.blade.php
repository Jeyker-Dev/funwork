<?php

use Livewire\Component;
use App\Models\Projects;

new class extends Component
{
    public function with(): array
    {
        return [
            'projects' => Projects::all(),
        ];
    }
};
?>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($projects as $project)
        <flux:card class="space-y-6 mt-10 line-card relative overflow-hidden">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center justify-center bg-orange-100 rounded-md w-10 h-10">
                    <flux:icon name="{{ $project->icon }}" variant="solid" class="text-amber-500 dark:text-amber-300" />
                </div>

                <flux:badge class="text-xs! px-4! sm:text-sm!" color="orange">
                    {{ $project->type }}
                </flux:badge>
            </div>

            <div>
                <flux:heading size="lg">{{ $project->name }}</flux:heading>
                <flux:text class="mt-2 leading-normal line-clamp-8 md:line-clamp-10 lg:line-clamp-12">{{ $project->description }}</flux:text>
            </div>

            <flux:spacer class="bg-gray-100 h-0.5"/>

            <div>
                <div class="flex flex-col items-start justify-start gap-3">
                    <div class="mt-6 flex items-center gap-4">
                        <flux:avatar size="lg" src="https://unavatar.io/x/taylorotwell" />

                        <div>
                            <flux:heading size="lg">{{ $project->owner->name }}</flux:heading>
                            <flux:text>Creator of Laravel</flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:badge class="text-xs! px-4! sm:text-sm!" color="gray">
                            {{ $project->status }}
                        </flux:badge>
                    </div>
                </div>
            </div>
        </flux:card>

    @endforeach
</div>

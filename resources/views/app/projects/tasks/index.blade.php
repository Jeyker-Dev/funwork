<x-layouts::app :title="__('Tasks')" :projectId="$project">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            <h1 class="text-xl md:text-2xl font-bold mb-4">Tasks</h1>

            <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                Manage and track your project tasks with your team in one place.
            </p>
        </div>

        @php
            $columns = [
                [
                    'key' => 'Todo',
                    'title' => 'Todo',
                    'dot' => 'bg-orange-500',
                    'border' => 'border-[rgba(255,128,64,0.48)]',
                    'bg' => 'bg-[rgba(255,128,64,0.16)]',
                ],
                [
                    'key' => 'Doing',
                    'title' => 'Doing',
                    'dot' => 'bg-yellow-500',
                    'border' => 'border-[rgba(255,220,70,0.38)]',
                    'bg' => 'bg-[rgba(255,220,70,0.17)]',
                ],
                [
                    'key' => 'Done',
                    'title' => 'Done',
                    'dot' => 'bg-green-500',
                    'border' => 'border-[rgba(38,186,112,0.33)]',
                    'bg' => 'bg-[rgba(38,186,112,0.13)]',
                ],
            ];
        @endphp

        <div class="grid grid-cols-3 gap-6">
            @foreach($columns as $column)
                <x-projects.tasks.card class="{{ $column['border'] }} {{ $column['bg'] }}">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="{{ $column['dot'] }} w-2 h-2 rounded-full"></div>

                        <p class="text-sm font-bold uppercase">
                            {{ $column['title'] }}
                        </p>

                        <livewire:projects.tasks.task-count :projectId="$project" :status="$column['key']" />
                    </div>

                    <div class="flex flex-col items-center gap-4 w-full">
                        <livewire:projects.tasks.create-task :projectId="$project" :status="$column['key']" />

                        <livewire:projects.tasks.show-tasks :projectId="$project" :status="$column['key']" />

                        <livewire:projects.tasks.task-more-indicator :projectId="$project" :status="$column['key']" />
                    </div>
                </x-projects.tasks.card>
            @endforeach
        </div>
    </div>
</x-layouts::app>

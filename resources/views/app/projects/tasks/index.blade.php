<x-layouts::app :title="__('Tasks')" :project="$project">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            <h1 class="text-xl md:text-2xl font-bold mb-4">Tasks</h1>

            <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                Manage and track your project tasks with your team in one place.
            </p>
        </div>

        <livewire:projects.tasks.task-board :project="$project" />
    </div>
</x-layouts::app>

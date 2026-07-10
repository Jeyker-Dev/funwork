<x-layouts::app :title="__('Tasks')" :project="$project">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <x-title-text 
            title="Your Tasks," 
            highlight="Organized."
            description="Manage and track your project tasks with your team in one place." 
        />

        <livewire:projects.tasks.task-board :project="$project" />
    </div>
</x-layouts::app>

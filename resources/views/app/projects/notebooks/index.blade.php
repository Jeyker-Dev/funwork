<x-layouts::app :title="__('Tasks')" :projectId="$project">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            <h1 class="text-xl md:text-2xl font-bold mb-4">Notebooks</h1>

            <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                Manage and track your project notebooks with your team in one place.
            </p>
        </div>

        <div class="flex items-center justify-center flex-col gap-4 mt-8">
            <flux:icon.book-open class="w-32 h-32 text-gray-400" />

            <div class="text-center">
                <flux:heading size="xl" class="mt-4 mb-2">No notebooks yet</flux:heading>

                <flux:text>
                    Create your first notebook to start capturing your project’s ideas and information.
                </flux:text>
            </div>

            <livewire:projects.notebooks.create-notebook/>
        </div>
    </div>
</x-layouts::app>

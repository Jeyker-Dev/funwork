<x-layouts::app :title="__('Tasks')" :projectId="$project">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            <h1 class="text-xl md:text-2xl font-bold mb-4">Tasks</h1>

            <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
                Here you can manage all your projects. Create new projects, view existing ones, and collaborate with
                your team.
            </p>
        </div>

        <div class="grid grid-cols-3 gap-6">
            <x-projects.tasks.card class="border-[rgba(255,128,64,0.48)] bg-[rgba(255,128,64,0.16)]">
                <p>
                    TODO
                </p>
            </x-projects.tasks.card>

            <x-projects.tasks.card class="border-[rgba(255,220,70,0.38)] bg-[rgba(255,220,70,0.17)]">
                <p>
                    DOING
                </p>
            </x-projects.tasks.card>

            <x-projects.tasks.card class="border-[rgba(38,186,112,0.33)] bg-[rgba(38,186,112,0.13)]">
                <p>
                    DONE
                </p>
            </x-projects.tasks.card>
        </div>
    </div>
</x-layouts::app>

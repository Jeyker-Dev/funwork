@props(['activities', 'project'])

<div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm p-6 flex flex-col h-full gap-4">
    <flux:heading size="lg">Recent Activity</flux:heading>
    
    @if($activities->isEmpty())
        <div class="flex flex-col items-center justify-center flex-1 text-center p-8 border-2 border-dashed border-neutral-200 dark:border-zinc-800 rounded-xl">
            <flux:icon.document-plus class="w-12 h-12 text-neutral-400 mb-4" />
            <flux:heading size="md" class="mb-2">No recent activity</flux:heading>
            <flux:text class="text-sm text-neutral-500 mb-4">Start by uploading a file or creating a notebook.</flux:text>
            <flux:button variant="primary" x-on:click="Flux.modal('upload-file').show()">
                Upload File
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-3 overflow-y-auto pr-2 pb-2">
            @foreach($activities as $activity)
                <div class="flex items-center justify-between p-3 rounded-xl border border-neutral-100 dark:border-zinc-800 bg-neutral-50 dark:bg-zinc-900/50">
                    <div class="flex items-center gap-3">
                        @if($activity->type === 'notebook')
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg dark:bg-blue-900/30 dark:text-blue-400">
                                <flux:icon.book-open class="w-5 h-5" />
                            </div>
                        @else
                            <div class="p-2 bg-purple-100 text-purple-600 rounded-lg dark:bg-purple-900/30 dark:text-purple-400">
                                <flux:icon.document class="w-5 h-5" />
                            </div>
                        @endif
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-neutral-900 dark:text-white">{{ $activity->name }}</span>
                            <span class="text-xs text-neutral-500">Updated {{ \Carbon\Carbon::parse($activity->updated_at)->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Ensure the modal is available on the dashboard -->
    <livewire:projects.files.upload-file-modal :project="$project" />
</div>

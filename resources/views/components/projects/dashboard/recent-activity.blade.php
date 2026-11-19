@props(['activities', 'project'])

<div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 flex flex-col h-full gap-4">
    <flux:heading size="lg">Recent Activity</flux:heading>
    
    @if($activities->isEmpty())
        <div class="flex flex-col items-center justify-center flex-1 text-center p-8 border-2 border-dashed border-ghost rounded-xl">
            <flux:icon.document-plus class="w-12 h-12 text-ink-subtle mb-4" />
            <flux:heading size="md" class="mb-2">No recent activity</flux:heading>
            <flux:text class="text-sm text-ink-muted mb-4">Start by uploading a file or creating a notebook.</flux:text>
            <flux:button variant="primary" class="max-md:min-h-11" x-on:click="Flux.modal('upload-file').show()">
                Upload File
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-3 overflow-y-auto pr-2 pb-2">
            @foreach($activities as $activity)
                <div class="flex items-center justify-between p-3 rounded-xl border border-ghost bg-surface-low">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="p-2 rounded-lg shrink-0 {{ $activity->type->iconClasses() }}">
                            <flux:icon :icon="$activity->type->icon()" class="w-5 h-5" />
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-medium text-ink truncate">{{ $activity->name }}</span>
                            <span class="text-xs text-ink-muted">Updated {{ $activity->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Ensure the modal is available on the dashboard -->
    <livewire:projects.files.upload-file-modal :project="$project" />
</div>

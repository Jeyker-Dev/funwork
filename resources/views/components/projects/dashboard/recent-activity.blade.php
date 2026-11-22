@props(['activities', 'project'])

<section aria-labelledby="recent-activity-heading" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-6 flex flex-col gap-5">
    <h2 id="recent-activity-heading" class="font-headline font-bold text-lg text-ink tracking-tight">Recent activity</h2>

    @if($activities->isEmpty())
        <div class="flex flex-col items-center justify-center text-center p-6 rounded-xl bg-surface-low gap-3">
            <div class="inline-flex items-center justify-center size-10 rounded-full bg-surface-card text-ink-subtle">
                <flux:icon name="document-plus" class="size-5" />
            </div>

            <div class="space-y-1">
                <p class="font-headline font-semibold text-base text-ink">No recent activity</p>
                <p class="text-sm text-ink-muted">Start by uploading a file or creating a notebook.</p>
            </div>

            <flux:button variant="primary" class="max-md:min-h-11" x-on:click="Flux.modal('upload-file').show()">
                Upload File
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach($activities as $activity)
                <div
                    wire:key="activity-{{ $activity->type->value }}-{{ $activity->id }}"
                    class="flex items-start gap-3 p-2.5 -mx-2.5 rounded-xl hover:bg-surface-low transition-colors"
                >
                    <div class="size-8 rounded-lg shrink-0 flex items-center justify-center mt-0.5 {{ $activity->type->iconClasses() }}">
                        <flux:icon :icon="$activity->type->icon()" class="size-4" />
                    </div>

                    <div class="flex flex-col min-w-0">
                        <p class="text-sm text-ink leading-snug break-words">
                            <span class="text-ink-muted">{{ $activity->type->actionLabel() }}</span>
                            <span class="font-medium text-ink">{{ $activity->name }}</span>
                        </p>
                        <span class="text-xs text-ink-subtle mt-0.5">{{ $activity->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <livewire:projects.files.upload-file-modal :project="$project" />
</section>

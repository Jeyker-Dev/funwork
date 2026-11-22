@php
    use App\Enums\TaskStatus;
@endphp

<div class="relative z-10 w-full max-w-[38rem] rounded-2xl border border-ghost bg-surface-card p-5 shadow-ambient xl:p-6">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-ghost pb-4">
        <div class="flex items-center gap-1.5 font-manrope text-xs font-semibold text-ink">
            <span class="font-normal text-ink-muted">Projects</span>
            <span class="text-ink-muted">/</span>
            <span>Website relaunch</span>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-brand/25 bg-brand-tint px-2.5 py-1 text-xs font-semibold text-brand-deep dark:text-orange-300">
                <span class="size-1.5 rounded-full bg-brand"></span>
                4 tasks this week
            </span>

            <div class="flex items-center -space-x-1">
                <span class="relative z-30 inline-flex size-7 items-center justify-center rounded-full bg-ink text-[10px] font-bold tracking-tight text-surface-card ring-2 ring-surface-card dark:bg-zinc-200 dark:text-zinc-900">AL</span>
                <span class="relative z-20 inline-flex size-7 items-center justify-center rounded-full bg-brand-strong text-[10px] font-bold tracking-tight text-white ring-2 ring-surface-card">SK</span>
                <span class="relative z-10 inline-flex size-7 items-center justify-center rounded-full bg-brand text-[10px] font-bold tracking-tight text-brand-ink ring-2 ring-surface-card">+2</span>
            </div>
        </div>
    </div>

    <div class="board-preview grid grid-cols-3 gap-3">
        <x-auth.board-column :status="TaskStatus::Todo" :count="2">
            <x-auth.board-card
                area="Docs"
                area-classes="bg-blue-50 text-blue-800 border-blue-200/70 dark:bg-blue-950/60 dark:text-blue-200 dark:border-blue-800/60"
                title="Write the onboarding notebook"
                initials="JM"
                avatar-classes="bg-surface-high text-ink dark:bg-zinc-700 dark:text-zinc-100"
            >
                <x-slot:meta><span class="shrink-0 whitespace-nowrap text-[11px] font-semibold text-ink-muted">P2</span></x-slot:meta>
                <x-slot:footer><span class="flex items-center gap-1 whitespace-nowrap text-ink-muted"><flux:icon.calendar variant="micro" class="size-3.5 shrink-0" /><span>Tomorrow</span></span></x-slot:footer>
            </x-auth.board-card>

            <x-auth.board-card
                area="Product"
                area-classes="bg-purple-50 text-purple-800 border-purple-200/70 dark:bg-purple-950/60 dark:text-purple-200 dark:border-purple-800/60"
                title="Plan the Q1 roadmap"
                initials="EK"
                avatar-classes="bg-purple-100 text-purple-900 dark:bg-purple-900/60 dark:text-purple-200"
            >
                <x-slot:meta><span class="shrink-0 whitespace-nowrap text-[11px] font-semibold text-ink-muted">P1</span></x-slot:meta>
                <x-slot:footer><span class="flex items-center gap-1 whitespace-nowrap text-ink-muted"><flux:icon.calendar variant="micro" class="size-3.5 shrink-0" /><span>Jan 20</span></span></x-slot:footer>
            </x-auth.board-card>
        </x-auth.board-column>

        <x-auth.board-column :status="TaskStatus::Doing" :count="1">
            <x-auth.board-card
                class="board-preview__follower"
                area="Backend"
                area-classes="bg-amber-50 text-amber-900 border-amber-200/70 dark:bg-amber-950/60 dark:text-amber-200 dark:border-amber-800/60"
                title="Connect the MCP inbox"
                initials="TN"
                avatar-classes="bg-amber-100 text-amber-900 dark:bg-amber-900/60 dark:text-amber-200"
            >
                <x-slot:meta><span class="shrink-0 whitespace-nowrap rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-950/60 dark:text-amber-200">60%</span></x-slot:meta>
                <x-slot:progress><div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-high dark:bg-zinc-700"><div class="h-full w-3/5 rounded-full bg-amber-500"></div></div></x-slot:progress>
                <x-slot:footer><span class="flex items-center gap-1 whitespace-nowrap text-ink-muted"><flux:icon.clock variant="micro" class="size-3.5 shrink-0" /><span>In progress</span></span></x-slot:footer>
            </x-auth.board-card>
        </x-auth.board-column>

        <x-auth.board-column :status="TaskStatus::Done" :count="2">
            <x-auth.board-card
                class="board-preview__mover relative z-20 h-[var(--board-card-h)] border-brand/40! shadow-ambient! ring-2 ring-brand/80 dark:border-brand/50!"
                area="Design"
                area-classes="border-brand/25 bg-brand-tint text-brand-deep dark:text-orange-300"
                title="Review the login redesign"
                initials="FW"
                avatar-classes="bg-brand text-brand-ink"
            >
                <x-slot:meta><flux:icon.check-circle variant="micro" class="size-4 shrink-0 text-brand-strong dark:text-orange-400" /></x-slot:meta>
                <x-slot:footer>
                    <div class="grid items-center">
                        <span class="board-preview__status-before col-start-1 row-start-1 flex items-center gap-1 whitespace-nowrap text-ink-muted"><flux:icon.clock variant="micro" class="size-3.5 shrink-0" /><span>In progress</span></span>
                        <span class="board-preview__status-after col-start-1 row-start-1 flex items-center gap-1 whitespace-nowrap font-semibold text-emerald-700 dark:text-emerald-400"><span class="size-1.5 shrink-0 rounded-full bg-emerald-600 dark:bg-emerald-400"></span><span>Completed</span></span>
                    </div>
                </x-slot:footer>
            </x-auth.board-card>

            <x-auth.board-card
                area="Frontend"
                area-classes="bg-emerald-50 text-emerald-800 border-emerald-200/70 dark:bg-emerald-950/60 dark:text-emerald-200 dark:border-emerald-800/60"
                title="Ship the new design tokens"
                initials="AL"
                avatar-classes="bg-emerald-100 text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-200"
            >
                <x-slot:meta><flux:icon.check-circle variant="micro" class="size-4 shrink-0 text-emerald-700 dark:text-emerald-400" /></x-slot:meta>
                <x-slot:footer><span class="whitespace-nowrap font-medium text-emerald-700 dark:text-emerald-400">Completed</span></x-slot:footer>
            </x-auth.board-card>
        </x-auth.board-column>
    </div>
</div>

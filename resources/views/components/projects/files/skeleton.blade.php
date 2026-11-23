<div class="flex flex-col w-full space-y-6">
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
        @for($i = 0; $i < 4; $i++)
            <div class="h-28 bg-surface-high border border-ghost rounded-2xl animate-pulse"></div>
        @endfor
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
        @for($i = 0; $i < 8; $i++)
            <div class="h-44 bg-surface-high border border-ghost rounded-2xl animate-pulse"></div>
        @endfor
    </div>
</div>

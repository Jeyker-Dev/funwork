<div class="flex flex-col w-full">
    <!-- Contextual Action Bar Skeleton -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div class="h-6 bg-gray-200 rounded w-48 animate-pulse"></div>
        <div class="flex gap-3">
            <div class="h-10 bg-gray-200 rounded w-64 animate-pulse"></div>
            <div class="h-10 bg-gray-200 rounded w-24 animate-pulse"></div>
            <div class="h-10 bg-gray-200 rounded w-32 animate-pulse"></div>
            <div class="h-10 bg-gray-200 rounded w-32 animate-pulse"></div>
        </div>
    </div>

    <!-- Folders Skeleton -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <div class="h-7 bg-gray-200 rounded w-24 animate-pulse"></div>
        </div>
        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
            @for($i=0; $i<4; $i++)
                <div class="h-32 bg-gray-200 border border-gray-100 rounded-xl animate-pulse"></div>
            @endfor
        </div>
    </div>

    <!-- Files Skeleton -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <div class="h-7 bg-gray-200 rounded w-32 animate-pulse"></div>
        </div>
        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
            @for($i=0; $i<8; $i++)
                <div class="h-48 bg-gray-200 border border-gray-100 rounded-xl animate-pulse"></div>
            @endfor
        </div>
    </div>
</div>

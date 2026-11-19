<div class="flex flex-col items-center justify-center py-10 px-4 min-h-[50vh]">
    <!-- Large Gray SVG Folder Wrapper -->
    <div class="bg-surface-high w-32 h-32 rounded-3xl flex items-center justify-center mb-8 shadow-inner">
        <flux:icon.folder variant="outline" class="w-16 h-16 text-ink-subtle stroke-[1.5]" />
    </div>

    <x-title-text 
        title="No Files or Folders," 
        highlight="Yet."
        description="Your workspace is a blank canvas. Start organizing your projects by creating a folder or uploading your first file." 
        class="text-center flex flex-col items-center mb-8" 
    />

    <div class="flex flex-wrap justify-center gap-4">
        <flux:modal.trigger name="upload-file">
            <flux:button variant="primary" icon="arrow-up-tray" class="max-md:min-h-11">Upload File</flux:button>
        </flux:modal.trigger>

        <flux:modal.trigger name="create-folder">
            <flux:button variant="outline" icon="folder-plus" class="max-md:min-h-11">Create Folder</flux:button>
        </flux:modal.trigger>
    </div>
</div>

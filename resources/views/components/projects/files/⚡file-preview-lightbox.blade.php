<?php

use Livewire\Component;

new class extends Component {
    public array $files = [];

    public function downloadCurrent(int $fileId): void
    {
        $this->dispatch('download-single-file', fileId: $fileId);
    }
}; ?>

<div style="display: contents;" x-data="{
    files: @entangle('files'),
    currentIndex: -1,
    isMobile: false,

    init() {
        this.isMobile = /Mobi|Android/i.test(navigator.userAgent) || window.innerWidth < 768;
    },

    get currentFile() {
        return this.currentIndex >= 0 && this.currentIndex < this.files.length 
            ? this.files[this.currentIndex] 
            : null;
    },

    get canPreview() {
        if (!this.currentFile) return false;
        
        const isImage = this.currentFile.mime_type.startsWith('image/');
        const isSvg = this.currentFile.mime_type === 'image/svg+xml';
        const isPdf = this.currentFile.mime_type === 'application/pdf';
        
        // Size strictly less than 15MB
        const isUnder15MB = this.currentFile.size < (15 * 1024 * 1024);

        if (!isUnder15MB) return false;

        if (isPdf) {
            return !this.isMobile;
        }

        return isImage || isSvg;
    },

    open(id) {
        const index = this.files.findIndex(f => f.id === id);
        if (index !== -1) {
            this.currentIndex = index;
            Flux.modal('file-preview-lightbox').show();
        }
    },

    next() {
        if (this.currentIndex < this.files.length - 1) {
            this.currentIndex++;
        }
    },

    prev() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
        }
    },

    downloadCurrent() {
        if (this.currentFile) {
            $wire.downloadCurrent(this.currentFile.id);
        }
    },

    handleKeydown(e) {
        if (e.key === 'ArrowRight') this.next();
        if (e.key === 'ArrowLeft') this.prev();
    }
}"
x-on:open-lightbox.window="open($event.detail.id)"
@keydown.window="if(Flux.modal('file-preview-lightbox')) handleKeydown($event)">

    <flux:modal name="file-preview-lightbox" class="w-full max-w-7xl p-0 bg-white/60 backdrop-blur-xl">
        <template x-if="currentFile">
            <div class="flex flex-col h-[90vh] w-full overflow-hidden relative">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 bg-black/10 backdrop-blur-sm shadow-sm z-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="text-white p-2 bg-black/20 rounded-lg">
                            <template x-if="currentFile.mime_type.startsWith('image/')">
                                <flux:icon.photo class="w-6 h-6" />
                            </template>
                            <template x-if="currentFile.mime_type === 'application/pdf'">
                                <flux:icon.document-text class="w-6 h-6" />
                            </template>
                            <template x-if="!currentFile.mime_type.startsWith('image/') && currentFile.mime_type !== 'application/pdf'">
                                <flux:icon.document class="w-6 h-6" />
                            </template>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-medium text-gray-900 truncate" x-text="currentFile.name"></h3>
                            <p class="text-sm text-gray-700" x-text="(currentFile.size / 1024 / 1024).toFixed(2) + ' MB'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" x-on:click="downloadCurrent">Download</flux:button>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 relative flex items-center justify-center bg-gray-100/50 backdrop-blur-md overflow-hidden">
                    
                    <!-- Navigation Previous -->
                    <button 
                        class="absolute left-4 z-50 p-2 rounded-full bg-white/80 shadow-md text-gray-700 hover:bg-white hover:text-orange-600 transition disabled:opacity-30 disabled:cursor-not-allowed"
                        x-on:click="prev()"
                        x-bind:disabled="currentIndex === 0"
                        title="Previous (Left Arrow)"
                    >
                        <flux:icon.chevron-left class="w-6 h-6" />
                    </button>

                    <!-- Viewer -->
                    <div class="w-full h-full flex items-center justify-center p-4">
                        <template x-if="canPreview">
                            <div class="relative w-full h-full flex items-center justify-center">
                                <!-- Loading Spinner for images -->
                                <template x-if="currentFile.mime_type.startsWith('image/')">
                                    <div class="absolute inset-0 flex items-center justify-center z-10" x-show="loadingImage" x-data="{ loadingImage: true }">
                                        <flux:icon.arrow-path class="w-8 h-8 animate-spin text-gray-400" />
                                    </div>
                                </template>

                                <!-- Image Viewer -->
                                <template x-if="currentFile.mime_type.startsWith('image/')">
                                    <img 
                                        x-bind:src="currentFile.preview_url" 
                                        class="max-w-full max-h-full object-contain relative z-20 transition-opacity duration-300"
                                        x-data="{ loadingImage: true }"
                                        x-on:load="loadingImage = false"
                                        x-bind:class="loadingImage ? 'opacity-0' : 'opacity-100'"
                                        alt="Preview"
                                    />
                                </template>

                                <!-- PDF Viewer -->
                                <template x-if="currentFile.mime_type === 'application/pdf'">
                                    <object 
                                        x-bind:data="currentFile.preview_url" 
                                        type="application/pdf" 
                                        class="w-full h-full rounded shadow-sm relative z-20"
                                    >
                                        <p class="p-4 bg-white rounded shadow text-center">
                                            Unable to display PDF. <a x-bind:href="currentFile.preview_url" class="text-blue-500 hover:underline">Download</a> instead.
                                        </p>
                                    </object>
                                </template>
                            </div>
                        </template>

                        <!-- Fallback / Unsupported -->
                        <template x-if="!canPreview">
                            <div class="flex flex-col items-center justify-center p-8 bg-white/90 backdrop-blur-md rounded-2xl shadow-xl max-w-sm text-center">
                                <div class="w-20 h-20 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mb-6">
                                    <flux:icon.document-arrow-down variant="solid" class="w-10 h-10" />
                                </div>
                                <h4 class="text-xl font-semibold text-gray-900 mb-2">Preview not available</h4>
                                <p class="text-gray-500 mb-6 text-sm">
                                    <span x-show="currentFile.size >= (15 * 1024 * 1024)">This file exceeds the 15MB preview limit.</span>
                                    <span x-show="currentFile.size < (15 * 1024 * 1024) && currentFile.mime_type === 'application/pdf' && isMobile">PDF previews are not supported on mobile devices.</span>
                                    <span x-show="currentFile.size < (15 * 1024 * 1024) && currentFile.mime_type !== 'application/pdf' && !currentFile.mime_type.startsWith('image/')">This file format is not supported for inline preview.</span>
                                </p>
                                <flux:button variant="primary" icon="arrow-down-tray" x-on:click="downloadCurrent">
                                    Download File
                                </flux:button>
                            </div>
                        </template>
                    </div>

                    <!-- Navigation Next -->
                    <button 
                        class="absolute right-4 z-50 p-2 rounded-full bg-white/80 shadow-md text-gray-700 hover:bg-white hover:text-orange-600 transition disabled:opacity-30 disabled:cursor-not-allowed"
                        x-on:click="next()"
                        x-bind:disabled="currentIndex === files.length - 1"
                        title="Next (Right Arrow)"
                    >
                        <flux:icon.chevron-right class="w-6 h-6" />
                    </button>

                </div>
            </div>
        </template>
    </flux:modal>
</div>

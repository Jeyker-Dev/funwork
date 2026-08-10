<?php

namespace App\Livewire\Forms;

use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Form;

class UploadFileForm extends Form
{
    public ?Project $project = null;

    public ?int $currentFolderId = null;

    #[Validate([
        'files' => 'required|array|min:1',
        'files.*' => 'required|file|max:102400',
    ])]
    public array $files = [];

    public function setContext(Project $project, ?int $currentFolderId = null): void
    {
        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function store(): void
    {
        $this->validate();

        foreach ($this->files as $file) {
            $name = $file->getClientOriginalName();
            $mime = $file->getMimeType();
            $size = $file->getSize();

            $disk = config('filesystems.default');
            $path = Storage::disk($disk)->putFile('projects/'.$this->project->id, $file);

            $this->project->files()->create([
                'name' => $name,
                'file_path' => $path,
                'disk' => $disk,
                'mime_type' => $mime,
                'size' => $size,
                'created_by' => Auth::id(),
                'folder_id' => $this->currentFolderId,
            ]);
        }

        $this->reset('files');

        Flux::toast(
            heading: 'Files Uploaded',
            text: 'The files have been successfully uploaded.',
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }
}

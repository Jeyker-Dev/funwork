<?php

namespace App\Livewire\Forms;

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Form;

class UploadFileForm extends Form
{
    public const int MAX_FILE_SIZE_KB = 102400;

    private const int KILOBYTES_PER_MEGABYTE = 1024;

    public ?Project $project = null;

    public ?int $currentFolderId = null;

    public array $files = [];

    public static function maxFileSizeLabel(): string
    {
        $megabytes = self::MAX_FILE_SIZE_KB / self::KILOBYTES_PER_MEGABYTE;
        $formatted = (float) (int) $megabytes === (float) $megabytes
            ? (string) (int) $megabytes
            : number_format($megabytes, 1);

        return "{$formatted} MB";
    }

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:'.self::MAX_FILE_SIZE_KB,
        ];
    }

    public function setContext(Project $project, ?int $currentFolderId = null): void
    {
        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function store(): void
    {
        $this->validate();

        Gate::authorize('create', [ProjectFile::class, $this->project]);

        if ($this->currentFolderId !== null) {
            $folder = Folder::findOrFail($this->currentFolderId);
            Gate::authorize('view', $folder);
            abort_if($folder->project_id !== $this->project->id, 403);
        }

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

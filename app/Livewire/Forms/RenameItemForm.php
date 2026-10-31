<?php

namespace App\Livewire\Forms;

use App\Models\Folder;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Form;

class RenameItemForm extends Form
{
    public ?ProjectFile $file = null;

    public ?Folder $folder = null;

    public string $name = '';

    public string $extension = '';

    public function setContextForFile(ProjectFile $file)
    {
        $this->file = $file;

        $parts = pathinfo($file->name);
        if (isset($parts['extension']) && $parts['extension'] !== '') {
            $this->name = $parts['filename'];
            $this->extension = '.'.$parts['extension'];
        } else {
            $this->name = $file->name;
            $this->extension = '';
        }
    }

    public function setContextForFolder(Folder $folder)
    {
        $this->folder = $folder;
        $this->name = $folder->name;
        $this->extension = '';
    }

    public function updateItem()
    {
        Gate::authorize('update', $this->file ?? $this->folder);

        $this->validate([
            'name' => ['required', 'string', 'max:255', 'min:1'],
        ]);

        $projectId = $this->file ? $this->file->project_id : $this->folder->project_id;
        $parentId = $this->file ? $this->file->folder_id : $this->folder->parent_id;
        $fullName = $this->name.$this->extension;

        // Custom validation: Uniqueness across both tables
        $existsAsFile = ProjectFile::where('project_id', $projectId)
            ->where('folder_id', $parentId)
            ->where('name', $fullName)
            ->when($this->file, function ($query) {
                $query->where('id', '!=', $this->file->id);
            })
            ->exists();

        $existsAsFolder = Folder::where('project_id', $projectId)
            ->where('parent_id', $parentId)
            ->where('name', $fullName)
            ->when($this->folder, function ($query) {
                $query->where('id', '!=', $this->folder->id);
            })
            ->exists();

        if ($existsAsFile || $existsAsFolder) {
            $this->addError('name', 'The name has already been taken.');

            return false;
        }

        if ($this->file) {
            $this->file->update(['name' => $fullName]);
        } else {
            $this->folder->update(['name' => $fullName]);
        }

        return true;
    }
}

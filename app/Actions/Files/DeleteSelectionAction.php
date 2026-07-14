<?php

namespace App\Actions\Files;

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Storage;

class DeleteSelectionAction
{
    public function execute(Project $project, array $selectedIds): void
    {
        foreach ($selectedIds as $item) {
            if (str_starts_with($item, 'file_')) {
                $fileId = str_replace('file_', '', $item);
                $file = $project->files()->find($fileId);

                if ($file) {
                    $this->deleteFile($file);
                }
            }

            if (str_starts_with($item, 'folder_')) {
                $folderId = str_replace('folder_', '', $item);
                $folder = $project->folders()->find($folderId);

                if ($folder) {
                    $this->deleteFolder($folder);
                }
            }
        }
    }

    private function deleteFile(ProjectFile $file): void
    {
        // We only soft delete in the DB as per the SoftDeletes trait.
        // We could also delete the physical file if it's a hard delete, 
        // but typically with SoftDeletes we keep the file or handle it differently.
        // For this action, we'll just soft delete the record.
        $file->delete();
    }

    private function deleteFolder(Folder $folder): void
    {
        // Recursively delete files
        foreach ($folder->files as $file) {
            $this->deleteFile($file);
        }

        // Recursively delete child folders
        foreach ($folder->children as $childFolder) {
            $this->deleteFolder($childFolder);
        }

        $folder->delete();
    }
}

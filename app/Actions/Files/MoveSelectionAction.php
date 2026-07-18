<?php

namespace App\Actions\Files;

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class MoveSelectionAction
{
    /**
     * @param Project $project The active project
     * @param array $selectedIds Format: ['file_1', 'folder_2']
     * @param int|null $targetFolderId Destination folder ID (null for root)
     * @throws ValidationException
     */
    public function execute(Project $project, array $selectedIds, ?int $targetFolderId): void
    {
        DB::transaction(function () use ($project, $selectedIds, $targetFolderId) {
            $targetFolder = null;
            if ($targetFolderId) {
                $targetFolder = Folder::where('project_id', $project->id)->find($targetFolderId);
            }

            foreach ($selectedIds as $idStr) {
                if (str_starts_with($idStr, 'file_')) {
                    $fileId = (int) substr($idStr, 5);
                    $file = ProjectFile::where('project_id', $project->id)->find($fileId);
                    if ($file) {
                        if ($file->folder_id === $targetFolderId) {
                            throw ValidationException::withMessages(['targetFolderId' => 'Source and target directories are identical.']);
                        }

                        $newName = $this->getUniqueFileName($project->id, $targetFolderId, $file->name);
                        $file->update(['folder_id' => $targetFolderId, 'name' => $newName]);
                    }
                } elseif (str_starts_with($idStr, 'folder_')) {
                    $folderId = (int) substr($idStr, 7);
                    $folder = Folder::where('project_id', $project->id)->find($folderId);
                    if ($folder) {
                        if ($folder->parent_id === $targetFolderId) {
                            throw ValidationException::withMessages(['targetFolderId' => 'Source and target directories are identical.']);
                        }

                        if ($targetFolder) {
                            $current = $targetFolder;
                            while ($current) {
                                if ($current->id === $folder->id) {
                                    throw ValidationException::withMessages(['targetFolderId' => 'Cannot move a folder into itself or its descendants.']);
                                }
                                $current = $current->parent;
                            }
                        }

                        $newName = $this->getUniqueFolderName($project->id, $targetFolderId, $folder->name);
                        $folder->update(['parent_id' => $targetFolderId, 'name' => $newName]);
                    }
                }
            }
        });
    }

    private function getUniqueFileName(int $projectId, ?int $targetFolderId, string $originalName): string
    {
        $newName = $originalName;
        $counter = 1;
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        while (ProjectFile::where('project_id', $projectId)->where('folder_id', $targetFolderId)->where('name', $newName)->exists()) {
            $newName = $extension ? "{$filename} ({$counter}).{$extension}" : "{$filename} ({$counter})";
            $counter++;
        }
        return $newName;
    }

    private function getUniqueFolderName(int $projectId, ?int $targetFolderId, string $originalName): string
    {
        $newName = $originalName;
        $counter = 1;
        while (Folder::where('project_id', $projectId)->where('parent_id', $targetFolderId)->where('name', $newName)->exists()) {
            $newName = "{$originalName} ({$counter})";
            $counter++;
        }
        return $newName;
    }
}

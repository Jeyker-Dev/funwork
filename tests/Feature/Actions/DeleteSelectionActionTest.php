<?php

use App\Actions\Files\DeleteSelectionAction;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;

test('deletes selected files and folders cascadingly', function () {
    $project = Project::factory()->create();

    $folder1 = Folder::factory()->create([
        'project_id' => $project->id,
        'name' => 'Folder 1',
    ]);

    $childFolder = Folder::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $folder1->id,
        'name' => 'Child Folder',
    ]);

    $file1 = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $folder1->id,
        'name' => 'file1.txt',
    ]);

    $file2 = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'name' => 'file2.txt',
    ]);

    $action = new DeleteSelectionAction;
    $action->execute($project, ['folder_'.$folder1->id, 'file_'.$file2->id]);

    expect($folder1->fresh()->trashed())->toBeTrue()
        ->and($childFolder->fresh()->trashed())->toBeTrue()
        ->and($file1->fresh()->trashed())->toBeTrue()
        ->and($file2->fresh()->trashed())->toBeTrue();
});

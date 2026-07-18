<?php

use App\Actions\Files\MoveSelectionAction;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('moves files and folders to a target folder', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $sourceFolder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $targetFolder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $sourceFolder->id,
        'created_by' => $user->id,
        'name' => 'file1.txt',
    ]);

    $folder = Folder::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $sourceFolder->id,
        'created_by' => $user->id,
        'name' => 'Subfolder',
    ]);

    $action = new MoveSelectionAction();
    $action->execute($project, ['file_'.$file->id, 'folder_'.$folder->id], $targetFolder->id);

    expect($file->fresh()->folder_id)->toBe($targetFolder->id);
    expect($folder->fresh()->parent_id)->toBe($targetFolder->id);
});

it('prevents moving to the same directory', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $folder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'created_by' => $user->id,
    ]);

    $action = new MoveSelectionAction();

    expect(fn () => $action->execute($project, ['file_'.$file->id], $folder->id))
        ->toThrow(ValidationException::class);
});

it('prevents inception paradox (moving folder into itself or descendant)', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    
    $parent = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $child = Folder::factory()->create(['project_id' => $project->id, 'parent_id' => $parent->id, 'created_by' => $user->id]);
    $grandchild = Folder::factory()->create(['project_id' => $project->id, 'parent_id' => $child->id, 'created_by' => $user->id]);

    $action = new MoveSelectionAction();

    // Move into itself
    expect(fn () => $action->execute($project, ['folder_'.$parent->id], $parent->id))
        ->toThrow(ValidationException::class);

    // Move into child
    expect(fn () => $action->execute($project, ['folder_'.$parent->id], $child->id))
        ->toThrow(ValidationException::class);

    // Move into grandchild
    expect(fn () => $action->execute($project, ['folder_'.$parent->id], $grandchild->id))
        ->toThrow(ValidationException::class);
});

it('auto-renames items on collision', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    
    $sourceFolder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $targetFolder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    // Create file and folder in target folder with names "document.txt" and "Subfolder"
    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $targetFolder->id,
        'created_by' => $user->id,
        'name' => 'document.txt',
    ]);
    Folder::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $targetFolder->id,
        'created_by' => $user->id,
        'name' => 'Subfolder',
    ]);

    // Create file and folder in source folder with the SAME names
    $movingFile = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $sourceFolder->id,
        'created_by' => $user->id,
        'name' => 'document.txt',
    ]);
    $movingFolder = Folder::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $sourceFolder->id,
        'created_by' => $user->id,
        'name' => 'Subfolder',
    ]);

    $action = new MoveSelectionAction();
    $action->execute($project, ['file_'.$movingFile->id, 'folder_'.$movingFolder->id], $targetFolder->id);

    expect($movingFile->fresh()->name)->toBe('document (1).txt');
    expect($movingFolder->fresh()->name)->toBe('Subfolder (1)');
});

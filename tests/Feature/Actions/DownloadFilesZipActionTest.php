<?php

use App\Actions\Files\DownloadFilesZipAction;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('generates zip with selected files and folders', function () {
    Storage::fake('local');

    $project = Project::factory()->create();

    $folder = Folder::factory()->create([
        'project_id' => $project->id,
        'name' => 'MyFolder',
    ]);

    $file1 = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'name' => 'file1.txt',
        'file_path' => 'files/file1.txt',
    ]);
    Storage::disk('local')->put('files/file1.txt', 'file 1 content');

    $file2 = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'name' => 'file2.txt',
        'file_path' => 'files/file2.txt',
    ]);
    Storage::disk('local')->put('files/file2.txt', 'file 2 content');

    $action = new DownloadFilesZipAction;

    $response = $action->execute($project, ['file_'.$file1->id, 'folder_'.$folder->id]);

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);

    $zipPath = $response->getFile()->getPathname();
    expect(file_exists($zipPath))->toBeTrue();

    $zip = new ZipArchive;
    $zip->open($zipPath);

    expect($zip->numFiles)->toBe(3)
        ->and($zip->getFromName('file1.txt'))->toBe('file 1 content')
        ->and($zip->getFromName('MyFolder/file2.txt'))->toBe('file 2 content');

    $zip->close();
});

test('throws exception if a requested file is missing', function () {
    Storage::fake('local');

    $project = Project::factory()->create();

    $file1 = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'name' => 'missing.txt',
        'file_path' => 'files/missing.txt',
    ]);

    $action = new DownloadFilesZipAction;
    $action->execute($project, ['file_'.$file1->id]);
})->throws(ValidationException::class, 'The requested file is unavailable.');

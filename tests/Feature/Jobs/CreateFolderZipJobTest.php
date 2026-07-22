<?php

use App\Jobs\CreateFolderZipJob;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Notifications\FolderZipFailedNotification;
use App\Notifications\FolderZippedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

it('creates a zip file and sends a notification on success', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $folder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    // Create some files
    $file1 = ProjectFile::factory()->create(['folder_id' => $folder->id, 'created_by' => $user->id, 'name' => 'file1.txt', 'file_path' => 'files/file1.txt']);
    $file2 = ProjectFile::factory()->create(['folder_id' => $folder->id, 'created_by' => $user->id, 'name' => 'file2.txt', 'file_path' => 'files/file2.txt']);

    Storage::disk('local')->put('files/file1.txt', 'content1');
    Storage::disk('local')->put('files/file2.txt', 'content2');

    $job = new CreateFolderZipJob($folder, $user);
    $job->handle();

    // Verify zip was created
    $zipPath = "exports/folder_{$folder->id}.zip";
    expect(Storage::disk('local')->exists($zipPath))->toBeTrue();

    // Verify zip contents
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($zipPath));
    expect($zip->numFiles)->toBe(2);
    expect($zip->getFromName('file1.txt'))->toBe('content1');
    expect($zip->getFromName('file2.txt'))->toBe('content2');
    $zip->close();

    // Verify notification was sent
    Notification::assertSentTo($user, FolderZippedNotification::class, function ($notification) use ($zipPath, $folder) {
        return $notification->zipPath === $zipPath && $notification->folder->id === $folder->id;
    });
});

it('sends a failure notification if zipping fails', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $folder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    // File exists in DB but missing from storage to simulate failure
    $file1 = ProjectFile::factory()->create(['folder_id' => $folder->id, 'created_by' => $user->id, 'name' => 'file1.txt', 'file_path' => 'files/file1.txt']);

    $job = new CreateFolderZipJob($folder, $user);
    $job->handle();

    Notification::assertSentTo($user, FolderZipFailedNotification::class, function ($notification) use ($folder) {
        return $notification->folder->id === $folder->id;
    });
});

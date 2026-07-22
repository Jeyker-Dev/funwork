<?php

namespace App\Jobs;

use App\Models\Folder;
use App\Models\User;
use App\Notifications\FolderZipFailedNotification;
use App\Notifications\FolderZippedNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CreateFolderZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes timeout for large zips

    public function __construct(
        public Folder $folder,
        public User $user
    ) {}

    public function handle(): void
    {
        try {
            $files = $this->folder->files; // files within this folder

            $zip = new ZipArchive;
            $zipPath = "exports/folder_{$this->folder->id}.zip";

            // Ensure exports directory exists
            if (! Storage::disk('local')->exists('exports')) {
                Storage::disk('local')->makeDirectory('exports');
            }

            $absolutePath = Storage::disk('local')->path($zipPath);

            if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Could not open zip archive at {$absolutePath}");
            }

            foreach ($files as $file) {
                $fileContent = Storage::disk('local')->get($file->file_path);
                if ($fileContent === null) {
                    // Simulating a failure if a file is missing
                    throw new Exception("File missing in storage: {$file->file_path}");
                }
                $zip->addFromString($file->name, $fileContent);
            }

            $zip->close();

            $this->user->notify(new FolderZippedNotification($this->folder, $zipPath));

        } catch (Exception $e) {
            Log::error('Folder zip failed: '.$e->getMessage());
            $this->user->notify(new FolderZipFailedNotification($this->folder));
        }
    }
}

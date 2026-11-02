<?php

namespace App\Actions\Files;

use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DownloadFilesZipAction
{
    public function execute(Project $project, array $selectedIds)
    {
        Gate::authorize('view', $project);

        if (count($selectedIds) === 1 && str_starts_with($selectedIds[0], 'file_')) {
            $fileId = str_replace('file_', '', $selectedIds[0]);
            $file = $project->files()->find($fileId);
            abort_unless($file, 404);

            Gate::authorize('view', $file);

            if (! Storage::disk($file->disk)->exists($file->file_path)) {
                throw ValidationException::withMessages([
                    'files' => 'The requested file is unavailable.',
                ]);
            }

            if ($file->disk === 'local') {
                $absolutePath = Storage::disk($file->disk)->path($file->file_path);

                return response()->download($absolutePath, $file->name);
            } else {
                $url = Storage::disk($file->disk)->temporaryUrl(
                    $file->file_path,
                    now()->addMinutes(60),
                    ['ResponseContentDisposition' => 'attachment; filename="'.$file->name.'"']
                );

                return redirect()->to($url);
            }
        }

        $zip = new ZipArchive;

        $zipFileName = 'download_'.time().'.zip';
        $zipPath = Storage::disk('local')->path('temp/'.$zipFileName);

        // Ensure temp directory exists
        if (! Storage::disk('local')->exists('temp')) {
            Storage::disk('local')->makeDirectory('temp');
        }

        $tempFilesToClean = [];

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

            try {
                foreach ($selectedIds as $item) {
                    if (str_starts_with($item, 'file_')) {
                        $fileId = str_replace('file_', '', $item);
                        $file = $project->files()->find($fileId);
                        abort_unless($file, 404);

                        Gate::authorize('view', $file);

                        if (! Storage::disk($file->disk)->exists($file->file_path)) {
                            throw ValidationException::withMessages([
                                'files' => 'One or more requested files are unavailable.',
                            ]);
                        }

                        if ($file->disk === 'local') {
                            $absolutePath = Storage::disk($file->disk)->path($file->file_path);
                            $zip->addFile($absolutePath, $file->name);
                        } else {
                            $tempId = uniqid('s3_');
                            Storage::disk('local')->put('temp/'.$tempId, Storage::disk($file->disk)->readStream($file->file_path));
                            $localTempPath = Storage::disk('local')->path('temp/'.$tempId);
                            $zip->addFile($localTempPath, $file->name);
                            $tempFilesToClean[] = 'temp/'.$tempId;
                        }
                    }

                    if (str_starts_with($item, 'folder_')) {
                        $folderId = str_replace('folder_', '', $item);
                        $folder = $project->folders()->find($folderId);
                        abort_unless($folder, 404);

                        Gate::authorize('view', $folder);
                        $this->addFolderToZip($zip, $folder, $folder->name.'/', $tempFilesToClean);
                    }
                }
            } catch (\Exception $e) {
                $zip->close();
                Storage::disk('local')->delete('temp/'.$zipFileName);
                foreach ($tempFilesToClean as $tempFile) {
                    Storage::disk('local')->delete($tempFile);
                }
                throw $e;
            }

            $zip->close();

            // Clean up temporary S3 files after zip is closed
            foreach ($tempFilesToClean as $tempFile) {
                Storage::disk('local')->delete($tempFile);
            }
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function addFolderToZip(ZipArchive $zip, $folder, $currentPath, array &$tempFilesToClean)
    {
        $zip->addEmptyDir($currentPath);

        foreach ($folder->files as $file) {
            if (! Storage::disk($file->disk)->exists($file->file_path)) {
                throw ValidationException::withMessages([
                    'files' => 'One or more requested files are unavailable.',
                ]);
            }

            if ($file->disk === 'local') {
                $absolutePath = Storage::disk($file->disk)->path($file->file_path);
                $zip->addFile($absolutePath, $currentPath.$file->name);
            } else {
                $tempId = uniqid('s3_');
                Storage::disk('local')->put('temp/'.$tempId, Storage::disk($file->disk)->readStream($file->file_path));
                $localTempPath = Storage::disk('local')->path('temp/'.$tempId);
                $zip->addFile($localTempPath, $currentPath.$file->name);
                $tempFilesToClean[] = 'temp/'.$tempId;
            }
        }

        foreach ($folder->children as $childFolder) {
            $this->addFolderToZip($zip, $childFolder, $currentPath.$childFolder->name.'/', $tempFilesToClean);
        }
    }
}

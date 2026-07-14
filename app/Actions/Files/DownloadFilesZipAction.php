<?php

namespace App\Actions\Files;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DownloadFilesZipAction
{
    public function execute(Project $project, array $selectedIds)
    {
        $zip = new ZipArchive;

        $zipFileName = 'download_'.time().'.zip';
        $zipPath = Storage::disk('local')->path('temp/'.$zipFileName);

        // Ensure temp directory exists
        if (! Storage::disk('local')->exists('temp')) {
            Storage::disk('local')->makeDirectory('temp');
        }

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

            try {
                foreach ($selectedIds as $item) {
                    if (str_starts_with($item, 'file_')) {
                        $fileId = str_replace('file_', '', $item);
                        $file = $project->files()->find($fileId);

                        if ($file) {
                            if (!Storage::disk($file->disk)->exists($file->file_path)) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'files' => 'One or more requested files are unavailable.'
                                ]);
                            }
                            $absolutePath = Storage::disk($file->disk)->path($file->file_path);
                            $zip->addFile($absolutePath, $file->name);
                        }
                    }

                    if (str_starts_with($item, 'folder_')) {
                        $folderId = str_replace('folder_', '', $item);
                        $folder = $project->folders()->find($folderId);

                        if ($folder) {
                            $this->addFolderToZip($zip, $folder, $folder->name.'/');
                        }
                    }
                }
            } catch (\Exception $e) {
                $zip->close();
                Storage::disk('local')->delete('temp/'.$zipFileName);
                throw $e;
            }

            $zip->close();
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function addFolderToZip(ZipArchive $zip, $folder, $currentPath)
    {
        $zip->addEmptyDir($currentPath);

        foreach ($folder->files as $file) {
            if (!Storage::disk($file->disk)->exists($file->file_path)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'files' => 'One or more requested files are unavailable.'
                ]);
            }
            $absolutePath = Storage::disk($file->disk)->path($file->file_path);
            $zip->addFile($absolutePath, $currentPath.$file->name);
        }

        foreach ($folder->children as $childFolder) {
            $this->addFolderToZip($zip, $childFolder, $currentPath.$childFolder->name.'/');
        }
    }
}

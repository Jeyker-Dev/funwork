<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProjectFilePreviewController extends Controller
{
    public function __invoke(Request $request, Project $project, ProjectFile $projectFile): Response|RedirectResponse
    {
        Gate::authorize('view', $project);
        Gate::authorize('view', $projectFile);
        abort_if($projectFile->project_id !== $project->id, 404);

        $disk = Storage::disk($projectFile->disk);

        if (! $disk->exists($projectFile->file_path)) {
            abort(404);
        }

        $driver = config("filesystems.disks.{$projectFile->disk}.driver");

        if ($driver === 's3') {
            return redirect($disk->temporaryUrl(
                $projectFile->file_path,
                now()->addMinutes(15),
                ['ResponseContentDisposition' => 'inline; filename="'.$projectFile->name.'"']
            ));
        }

        return $disk->response($projectFile->file_path, $projectFile->name, [
            'Content-Disposition' => 'inline; filename="'.$projectFile->name.'"',
        ]);
    }
}

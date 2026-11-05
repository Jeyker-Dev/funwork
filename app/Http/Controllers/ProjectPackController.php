<?php

namespace App\Http\Controllers;

use App\Actions\Projects\BuildProjectPackAction;
use App\Models\Project;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectPackController extends Controller
{
    public function __invoke(Project $project, BuildProjectPackAction $buildPack): StreamedResponse
    {
        $markdown = $buildPack->execute($project);

        return response()->streamDownload(
            function () use ($markdown) {
                echo $markdown;
            },
            Str::slug($project->name).'-project-pack.md',
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        );
    }
}

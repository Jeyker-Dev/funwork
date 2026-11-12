<?php

namespace App\Actions\Projects;

use App\Models\Project;

class CreateDefaultAreasAction
{
    /**
     * The 4 default areas created for every project.
     */
    public const DEFAULTS = [
        ['name' => 'Frontend', 'slug' => 'frontend', 'color' => 'blue', 'position' => 1],
        ['name' => 'Backend', 'slug' => 'backend', 'color' => 'green', 'position' => 2],
        ['name' => 'Database', 'slug' => 'database', 'color' => 'amber', 'position' => 3],
        ['name' => 'Design', 'slug' => 'design', 'color' => 'purple', 'position' => 4],
    ];

    /**
     * Create the default areas for the given project.
     */
    public function __invoke(Project $project): void
    {
        foreach (self::DEFAULTS as $default) {
            $project->areas()->create($default);
        }
    }
}

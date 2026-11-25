<?php

namespace App\Exceptions\RepositoryConnections;

use App\Exceptions\BusinessRuleException;
use App\Models\Area;
use App\Models\Project;

final class DuplicateRepositoryConnectionException extends BusinessRuleException
{
    public static function withoutArea(Project $project): self
    {
        return new self("Project [{$project->name}] already has a repository connection without an area.");
    }

    public static function forArea(Project $project, Area $area): self
    {
        return new self("Project [{$project->name}] already has a repository connection for area [{$area->name}].");
    }
}

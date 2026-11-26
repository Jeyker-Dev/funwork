<?php

namespace App\Exceptions\RepositoryConnections;

use App\Exceptions\BusinessRuleException;
use App\Models\Area;
use App\Models\Project;

final class AreaDoesNotBelongToProjectException extends BusinessRuleException
{
    public static function for(Area $area, Project $project): self
    {
        return new self("Area [{$area->name}] does not belong to project [{$project->name}].");
    }
}

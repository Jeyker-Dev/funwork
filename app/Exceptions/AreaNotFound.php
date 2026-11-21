<?php

namespace App\Exceptions;

use App\Models\Project;

final class AreaNotFound extends BusinessRuleException
{
    public static function for(string $key, Project $project): self
    {
        return new self("Area [{$key}] not found in project [{$project->id}].");
    }
}

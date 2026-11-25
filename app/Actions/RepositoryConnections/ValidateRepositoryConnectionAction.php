<?php

namespace App\Actions\RepositoryConnections;

use App\Data\RepositoryConnections\RepositoryConnectionData;
use App\Exceptions\RepositoryConnections\AreaDoesNotBelongToProjectException;
use App\Exceptions\RepositoryConnections\DuplicateRepositoryConnectionException;
use App\Exceptions\RepositoryConnections\InvalidBranchException;
use App\Exceptions\RepositoryConnections\InvalidDocsPathException;
use App\Exceptions\RepositoryConnections\InvalidRepositoryException;
use App\Models\Area;
use App\Models\Project;
use App\Models\RepositoryConnection;

/**
 * Validates repository connection metadata and project/area uniqueness rules.
 */
final class ValidateRepositoryConnectionAction
{
    private const string REPOSITORY_PATTERN = '/^[A-Za-z0-9_-]+\/[A-Za-z0-9_.-]+$/';

    public function __invoke(
        Project $project,
        RepositoryConnectionData $input,
        ?RepositoryConnection $existing = null,
    ): void {
        $this->ensureValidRepository($input->repository);

        if (! $this->isSafeRelativePath($input->branch)) {
            throw InvalidBranchException::forValue($input->branch);
        }

        if (! $this->isSafeRelativePath($input->docsPath)) {
            throw InvalidDocsPathException::forValue($input->docsPath);
        }

        $this->ensureAreaBelongsToProject($project, $input->area);
        $this->ensureUniqueForProjectAndArea($project, $input->area, $existing);
    }

    private function ensureValidRepository(string $repository): void
    {
        if (preg_match(self::REPOSITORY_PATTERN, $repository) !== 1 || str_contains($repository, '..')) {
            throw InvalidRepositoryException::forValue($repository);
        }

        [, $name] = explode('/', $repository, 2);

        if ($name === '.') {
            throw InvalidRepositoryException::forValue($repository);
        }
    }

    private function isSafeRelativePath(string $value): bool
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            return false;
        }

        if (
            str_starts_with($value, '/')
            || str_ends_with($value, '/')
            || str_contains($value, '\\')
            || str_contains($value, "\0")
            || str_contains($value, '..')
        ) {
            return false;
        }

        foreach (explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.') {
                return false;
            }
        }

        return true;
    }

    private function ensureAreaBelongsToProject(Project $project, ?Area $area): void
    {
        if ($area instanceof Area && $area->project_id !== $project->id) {
            throw AreaDoesNotBelongToProjectException::for($area, $project);
        }
    }

    private function ensureUniqueForProjectAndArea(
        Project $project,
        ?Area $area,
        ?RepositoryConnection $existing,
    ): void {
        $query = RepositoryConnection::query()->where('project_id', $project->id);

        if ($existing instanceof RepositoryConnection) {
            $query->where('id', '!=', $existing->id);
        }

        if ($area instanceof Area) {
            if ($query->where('area_id', $area->id)->exists()) {
                throw DuplicateRepositoryConnectionException::forArea($project, $area);
            }

            return;
        }

        // SQL unique indexes treat NULL as distinct, so project-wide connections are checked explicitly.
        if ($query->whereNull('area_id')->exists()) {
            throw DuplicateRepositoryConnectionException::withoutArea($project);
        }
    }
}

<?php

namespace App\Actions\Tasks;

use App\Data\LineValidationData;
use App\Data\ParsedSegmentsData;
use App\Data\ParsedTaskLineData;
use App\Data\ResolvedAreaData;
use App\Data\StatusExtractionData;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;

class ParseTaskImportAction
{
    public const MAX_LINES = 50;

    public const MAX_TASKS = self::MAX_LINES;

    public const MAX_TITLE_LENGTH = 255;

    public const DONE_MARKER = 'x';

    public const NO_DEPENDENCY_MARKER = '-';

    /**
     * @return array<ParsedTaskLineData>
     */
    public function __invoke(Project $project, string $text): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $existingTitles = Task::where('project_id', $project->id)
            ->pluck('title')
            ->map(fn (string $title): string => mb_strtolower($title))
            ->all();

        return collect($rawLines)
            ->map(fn (string $rawLine): string => trim($rawLine))
            ->reject(fn (string $trimmedLine): bool => blank($trimmedLine))
            ->map(fn (string $trimmedLine): ParsedTaskLineData => $this->parseLine($project, $trimmedLine, $existingTitles))
            ->values()
            ->all();
    }

    /**
     * @param  array<string>  $existingTitles
     */
    private function parseLine(Project $project, string $trimmedLine, array $existingTitles): ParsedTaskLineData
    {
        $statusExtraction = $this->extractStatus($trimmedLine);
        $resolvedArea = $this->resolveArea($project, $statusExtraction->remainder);
        $segments = $this->splitSegments($resolvedArea->remainder);
        $validation = $this->validateLine($segments->title, $segments->criterion, $existingTitles);

        $warnings = $resolvedArea->warning
            ? array_merge([$resolvedArea->warning], $validation->warnings)
            : $validation->warnings;

        return new ParsedTaskLineData(
            rawLine: $trimmedLine,
            title: $segments->title,
            criterion: $segments->criterion,
            dependency: $segments->dependency,
            status: $statusExtraction->status,
            priority: TaskPriority::Low,
            areaTag: $resolvedArea->areaTag,
            areaId: $resolvedArea->area?->id,
            areaName: $resolvedArea->area?->name,
            areaColor: $resolvedArea->area?->color,
            errors: $validation->errors,
            warnings: $warnings,
        );
    }

    private function extractStatus(string $line): StatusExtractionData
    {
        if (preg_match('/^-\s*\[([ xX])\]\s*/u', $line, $matches)) {
            $isDone = strtolower($matches[1]) === self::DONE_MARKER;
            $remainder = (string) substr($line, strlen($matches[0]));

            return new StatusExtractionData(
                status: $isDone ? TaskStatus::Done : TaskStatus::Todo,
                remainder: $remainder,
            );
        }

        return new StatusExtractionData(
            status: TaskStatus::Todo,
            remainder: $line,
        );
    }

    private function resolveArea(Project $project, string $line): ResolvedAreaData
    {
        if (! preg_match('/^\[([^\]]+)\]\s*/u', $line, $matches)) {
            return new ResolvedAreaData(
                areaTag: null,
                area: null,
                warning: null,
                remainder: $line,
            );
        }

        $areaTag = trim($matches[1]);
        $remainder = (string) substr($line, strlen($matches[0]));
        $area = $project->areaByKey($areaTag);

        if ($area) {
            return new ResolvedAreaData(
                areaTag: $areaTag,
                area: $area,
                warning: null,
                remainder: $remainder,
            );
        }

        $warning = "Area '{$areaTag}' not found — will be imported without area";

        return new ResolvedAreaData(
            areaTag: $areaTag,
            area: null,
            warning: $warning,
            remainder: $remainder,
        );
    }

    private function splitSegments(string $line): ParsedSegmentsData
    {
        $parts = preg_split('/\s*(?:—|–)\s*|\s+--\s+/u', $line);
        if ($parts === false) {
            $parts = [$line];
        }

        $parts = array_map('trim', $parts);

        if (count($parts) === 1) {
            return new ParsedSegmentsData(
                title: $parts[0],
                criterion: '',
                dependency: null,
            );
        }

        $lastPart = end($parts);
        if (preg_match('/^(?:depende\s+de|depends\s+on)\s*:\s*(.*)$/iu', $lastPart, $depMatch)) {
            $depValue = trim($depMatch[1]);
            $dependency = ($depValue === self::NO_DEPENDENCY_MARKER || $depValue === '') ? null : $depValue;
            $criterionParts = array_slice($parts, 1, -1);
            $criterion = trim(implode(' — ', $criterionParts));

            return new ParsedSegmentsData(
                title: $parts[0],
                criterion: $criterion,
                dependency: $dependency,
            );
        }

        $criterionParts = array_slice($parts, 1);
        $criterion = trim(implode(' — ', $criterionParts));

        return new ParsedSegmentsData(
            title: $parts[0],
            criterion: $criterion,
            dependency: null,
        );
    }

    /**
     * @param  array<string>  $existingTitles
     */
    private function validateLine(string $title, string $criterion, array $existingTitles): LineValidationData
    {
        $errors = [];
        $warnings = [];

        if ($title === '') {
            $errors[] = 'Missing title';
        } elseif (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $errors[] = 'Title cannot be longer than '.self::MAX_TITLE_LENGTH.' characters';
        }

        if ($criterion === '') {
            $errors[] = 'Missing acceptance criterion';
        }

        if ($title !== '' && in_array(mb_strtolower($title), $existingTitles, true)) {
            $warnings[] = 'Title already exists in the project (possible duplicate)';
        }

        return new LineValidationData(
            errors: $errors,
            warnings: $warnings,
        );
    }
}

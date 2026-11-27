<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectHealthCheckData;
use App\Data\Projects\ProjectHealthData;
use App\Enums\HealthCheckSeverity;
use App\Enums\RepositoryConnectionStatus;
use App\Enums\TaskStatus;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Support\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GetProjectHealthAction
{
    public const int STALE_DOING_DAYS = 7;

    public const int STALE_INBOX_DAYS = 7;

    public const int STALE_DOCS_COMPLETED_TASKS_THRESHOLD = 10;

    public const int MIN_TASKS_FOR_DECISIONS_THRESHOLD = 10;

    public const int DEFAULT_VISIBLE_CHECKS = 3;

    /**
     * Evaluate deterministic project health rules using constant queries and the user's timezone.
     */
    public function __invoke(Project $project, User $user): ProjectHealthData
    {
        $timezone = DateHelper::userTimezone($user);
        $localToday = DateHelper::userToday($user);

        $stuckDoingCutoffUtc = CarbonImmutable::parse($localToday, $timezone)
            ->subDays(self::STALE_DOING_DAYS)
            ->startOfDay()
            ->utc();

        $staleInboxCutoffUtc = CarbonImmutable::parse($localToday, $timezone)
            ->subDays(self::STALE_INBOX_DAYS)
            ->startOfDay()
            ->utc();

        $brokenConnections = $project->repositoryConnections()
            ->where('status', RepositoryConnectionStatus::Broken->value)
            ->orderBy('id')
            ->get(['id', 'repository', 'last_error']);

        $projectMetrics = Project::query()
            ->whereKey($project->id)
            ->withCount([
                'tasks',
                'decisions',
                'tasks as stuck_doing_tasks_count' => fn (Builder $query): Builder => $query
                    ->where('status', TaskStatus::Doing->value)
                    ->where('status_changed_at', '<', $stuckDoingCutoffUtc),
                'tasks as stale_docs_tasks_count' => fn (Builder $query): Builder => $query
                    ->where('status', TaskStatus::Done->value)
                    ->whereNotNull('completed_at')
                    ->where('completed_at', '>', Notebook::query()
                        ->selectRaw('MAX(content_changed_at)')
                        ->whereColumn('notebooks.project_id', 'tasks.project_id')
                        ->toBase()),
                'inboxItems as stale_inbox_items_count' => fn (Builder $query): Builder => $query
                    ->where('user_id', $user->id)
                    ->where('created_at', '<', $staleInboxCutoffUtc),
            ])
            ->first();

        if ($projectMetrics === null) {
            return ProjectHealthData::empty();
        }

        $checks = collect();

        foreach ($brokenConnections as $connection) {
            $checks->push($this->buildBrokenConnectionCheck($project, $connection));
        }

        $stuckDoingCount = (int) $projectMetrics->stuck_doing_tasks_count;
        if ($stuckDoingCount > 0) {
            $checks->push($this->buildStuckDoingCheck($project, $stuckDoingCount));
        }

        $staleDocsCount = (int) $projectMetrics->stale_docs_tasks_count;
        if ($staleDocsCount >= self::STALE_DOCS_COMPLETED_TASKS_THRESHOLD) {
            $checks->push($this->buildStaleDocsCheck($project, $staleDocsCount));
        }

        $totalTasksCount = (int) $projectMetrics->tasks_count;
        $decisionsCount = (int) $projectMetrics->decisions_count;
        if ($totalTasksCount >= self::MIN_TASKS_FOR_DECISIONS_THRESHOLD && $decisionsCount === 0) {
            $checks->push($this->buildNoDecisionsCheck($project, $totalTasksCount));
        }

        $staleInboxCount = (int) $projectMetrics->stale_inbox_items_count;
        if ($staleInboxCount > 0) {
            $checks->push($this->buildStaleInboxCheck($project, $staleInboxCount));
        }

        $orderedChecks = $checks
            ->sortBy(fn (ProjectHealthCheckData $check): int => $check->severity->sortOrder())
            ->values();

        return new ProjectHealthData(checks: $orderedChecks);
    }

    private function buildBrokenConnectionCheck(Project $project, RepositoryConnection $connection): ProjectHealthCheckData
    {
        $error = filled($connection->last_error) ? $connection->last_error : 'Sync failed.';

        return new ProjectHealthCheckData(
            key: "broken_repository_connection_{$connection->id}",
            severity: HealthCheckSeverity::Danger,
            title: 'Repository sync is broken',
            explanation: "{$connection->repository}: {$error}",
            count: 1,
            linkLabel: 'Fix in settings',
            linkUrl: route('project.settings', $project).'#repositories',
        );
    }

    private function buildStuckDoingCheck(Project $project, int $stuckDoingCount): ProjectHealthCheckData
    {
        $taskWord = Str::plural('task', $stuckDoingCount);

        return new ProjectHealthCheckData(
            key: 'stuck_doing_tasks',
            severity: HealthCheckSeverity::Warning,
            title: "{$stuckDoingCount} {$taskWord} stuck in Doing",
            explanation: 'No status change for more than '.self::STALE_DOING_DAYS.' days.',
            count: $stuckDoingCount,
            linkLabel: 'Review tasks',
            linkUrl: route('project.tasks', $project),
        );
    }

    private function buildStaleDocsCheck(Project $project, int $staleDocsCount): ProjectHealthCheckData
    {
        return new ProjectHealthCheckData(
            key: 'stale_docs',
            severity: HealthCheckSeverity::Notice,
            title: 'Docs may be out of date',
            explanation: "{$staleDocsCount} tasks were completed since the notebooks were last updated.",
            count: $staleDocsCount,
            linkLabel: 'Open notebooks',
            linkUrl: route('project.notebooks', $project),
        );
    }

    private function buildNoDecisionsCheck(Project $project, int $totalTasksCount): ProjectHealthCheckData
    {
        return new ProjectHealthCheckData(
            key: 'no_decisions',
            severity: HealthCheckSeverity::Notice,
            title: 'No decisions logged yet',
            explanation: "This project has {$totalTasksCount} tasks and no architecture decisions recorded.",
            count: $totalTasksCount,
            linkLabel: 'Log a decision',
            linkUrl: route('project.decisions', $project),
        );
    }

    private function buildStaleInboxCheck(Project $project, int $staleInboxCount): ProjectHealthCheckData
    {
        $ideaWord = Str::plural('idea', $staleInboxCount);

        return new ProjectHealthCheckData(
            key: 'stale_inbox',
            severity: HealthCheckSeverity::Notice,
            title: "{$staleInboxCount} {$ideaWord} waiting in the Inbox",
            explanation: 'Captured more than a week ago.',
            count: $staleInboxCount,
            linkLabel: 'Process inbox',
            linkUrl: route('project.inbox', $project),
        );
    }
}

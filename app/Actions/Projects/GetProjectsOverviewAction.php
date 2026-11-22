<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectsOverviewData;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GetProjectsOverviewAction
{
    private const int MORNING_START_HOUR = 5;

    private const int AFTERNOON_START_HOUR = 12;

    private const int EVENING_START_HOUR = 18;

    public function __construct(
        private readonly ResolveContinueProjectAction $resolveContinueProject,
    ) {}

    /**
     * Build the projects index overview for the authenticated user without N+1 queries.
     */
    public function __invoke(User $user): ProjectsOverviewData
    {
        $timezone = $user->timezone ?? config('app.timezone', 'UTC');
        $localNow = now($timezone);

        $projects = Project::query()
            ->where('owner_id', $user->id)
            ->withTaskCounts()
            ->withMax('tasks', 'updated_at')
            ->withMax('notebooks', 'updated_at')
            ->withMax('files', 'updated_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $activeProjectsCount = $user->projects()
            ->whereNull('archived_at')
            ->whereRaw('LOWER(TRIM(status)) = ?', [ProjectStatus::Active->value])
            ->count();

        $tasksDueThisWeekCount = Task::query()
            ->whereHas('project', fn (Builder $query): Builder => $query
                ->where('owner_id', $user->id)
                ->whereNull('archived_at'))
            ->dueThisWeek($localNow->toDateString())
            ->count();

        return new ProjectsOverviewData(
            greeting: $this->greetingFor($user, $localNow),
            activeProjectsCount: $activeProjectsCount,
            tasksDueThisWeekCount: $tasksDueThisWeekCount,
            continueProject: ($this->resolveContinueProject)($projects),
            projects: $projects,
        );
    }

    private function greetingFor(User $user, CarbonInterface $localNow): string
    {
        $hour = $localNow->hour;

        $prefix = match (true) {
            $hour >= self::MORNING_START_HOUR && $hour < self::AFTERNOON_START_HOUR => 'Good morning',
            $hour >= self::AFTERNOON_START_HOUR && $hour < self::EVENING_START_HOUR => 'Good afternoon',
            default => 'Good evening',
        };

        $firstName = Str::of($user->name)->trim()->explode(' ')->first() ?: $user->display_name;

        return "{$prefix}, {$firstName}";
    }
}

<?php

use App\Enums\ChangelogEntryLabel;
use App\Models\Project;
use App\Models\User;
use App\Services\ChangelogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

it('parses markdown changelog files, groups by date newest first, and resolves links safely', function () {
    $tempDir = storage_path('framework/testing/changelog-'.uniqid());
    File::ensureDirectoryExists($tempDir);

    try {
        File::put($tempDir.'/2026-11-20-older.md', <<<'MD'
        ---
        date: 2026-11-20
        entries:
          - label: Fixed
            title: Broken sidebar spacing
            description: Fixed padding on narrow screens.
            route: nonexistent.route
            link_label: Open broken
        ---
        MD);

        File::put($tempDir.'/2026-11-22-first.md', <<<'MD'
        ---
        date: 2026-11-22
        entries:
          - label: New
            title: Tasks board
            description: Drag tasks between columns.
            route: project.tasks
            link_label: Open Tasks
        ---
        MD);

        File::put($tempDir.'/2026-11-22-second.md', <<<'MD'
        ---
        date: 2026-11-22
        entries:
          - label: Improved
            title: Projects list
            description: Continue where you left off.
            route: projects
            link_label: Open Projects
        ---
        MD);

        $userWithProject = User::factory()->create();
        $project = Project::factory()->create([
            'owner_id' => $userWithProject->id,
            'updated_at' => Carbon::parse('2026-11-22 10:00:00'),
        ]);

        $userWithoutProject = User::factory()->create();

        $service = new ChangelogService($tempDir);

        expect($service->latestReleaseDate()?->toDateString())->toBe('2026-11-22');

        $releases = $service->releases($userWithProject, Carbon::parse('2026-11-21 12:00:00'));

        expect($releases)->toHaveCount(2)
            ->and($releases[0]->date->toDateString())->toBe('2026-11-22')
            ->and($releases[0]->formattedDate)->toBe('November 22, 2026')
            ->and($releases[0]->isNewSinceLastVisit)->toBeTrue()
            ->and($releases[0]->entries)->toHaveCount(2)
            ->and($releases[0]->entries[0]->label)->toBe(ChangelogEntryLabel::New)
            ->and($releases[0]->entries[0]->url)->toBe(route('project.tasks', ['project' => $project]))
            ->and($releases[0]->entries[1]->label)->toBe(ChangelogEntryLabel::Improved)
            ->and($releases[0]->entries[1]->url)->toBe(route('projects'));

        expect($releases[1]->date->toDateString())->toBe('2026-11-20')
            ->and($releases[1]->isNewSinceLastVisit)->toBeFalse()
            ->and($releases[1]->entries[0]->label)->toBe(ChangelogEntryLabel::Fixed)
            ->and($releases[1]->entries[0]->url)->toBeNull();

        $releasesWithoutProject = $service->releases($userWithoutProject);
        expect($releasesWithoutProject[0]->entries[0]->url)->toBeNull()
            ->and($releasesWithoutProject[0]->entries[1]->url)->toBe(route('projects'));
    } finally {
        File::deleteDirectory($tempDir);
    }
});

it('returns empty releases and null latest date when the changelog directory is missing or empty', function () {
    $missingService = new ChangelogService(storage_path('framework/testing/does-not-exist-'.uniqid()));

    expect($missingService->releases())->toBeEmpty()
        ->and($missingService->latestReleaseDate())->toBeNull();
});

it('seeds resources/changelog with all real shipped releases in English', function () {
    $files = glob(resource_path('changelog/*.md'));

    expect($files)->toHaveCount(7);

    $userWithProject = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $userWithProject->id]);
    $userWithoutProject = User::factory()->create();

    $service = app(ChangelogService::class);
    $releases = $service->releases($userWithProject);

    expect($releases->map(fn ($release) => $release->date->toDateString())->all())
        ->toBe(['2026-11-26', '2026-11-25', '2026-11-24', '2026-11-23', '2026-11-22', '2026-11-21'])
        ->and($releases->map(fn ($release) => $release->formattedDate)->all())
        ->toBe(['November 26, 2026', 'November 25, 2026', 'November 24, 2026', 'November 23, 2026', 'November 22, 2026', 'November 21, 2026']);

    $allTitles = $releases
        ->flatMap(fn ($release) => $release->entries->pluck('title'))
        ->implode(' | ');

    expect($allTitles)
        ->toContain('GitHub repository docs sync')
        ->toContain('Daily digest by email')
        ->toContain('Copy context for AI')
        ->toContain('MCP project kickoff')
        ->toContain('Global quick capture')
        ->toContain('Account settings')
        ->toContain('Project settings')
        ->toContain('Notebooks')
        ->toContain('Files')
        ->toContain('One search per screen')
        ->toContain('Optional notebook descriptions')
        ->toContain('Project history')
        ->toContain("What's new")
        ->toContain('sign-in')
        ->toContain('Projects')
        ->toContain('Project home')
        ->toContain('Inbox')
        ->toContain('Tasks board')
        ->toContain('Tasks without a description')
        ->toContain('Decisions');

    $entriesByTitle = $releases
        ->flatMap(fn ($release) => $release->entries)
        ->keyBy('title');

    expect($entriesByTitle['GitHub repository docs sync']->url)->toBe(route('project.settings', ['project' => $project]))
        ->and($entriesByTitle['Daily digest by email']->url)->toBe(route('notifications.edit'))
        ->and($entriesByTitle['Copy context for AI']->url)->toBe(route('projects.show', ['project' => $project]))
        ->and($entriesByTitle['MCP project kickoff']->url)->toBe(route('integrations.edit'))
        ->and($entriesByTitle['Global quick capture']->url)->toBe(route('project.inbox', ['project' => $project]))
        ->and($entriesByTitle['Account settings']->url)->toBe(route('profile.edit'))
        ->and($entriesByTitle['Project settings']->url)->toBe(route('project.settings', ['project' => $project]))
        ->and($entriesByTitle['Notebooks']->url)->toBe(route('project.notebooks', ['project' => $project]))
        ->and($entriesByTitle['Files']->url)->toBe(route('project.files', ['project' => $project]))
        ->and($entriesByTitle['Project history']->url)->toBe(route('project.history', ['project' => $project]))
        ->and($entriesByTitle["What's new"]->url)->toBeNull()
        ->and($entriesByTitle['One search per screen']->url)->toBeNull()
        ->and($entriesByTitle['Optional notebook descriptions']->url)->toBeNull();

    $withoutProjectEntries = $service->releases($userWithoutProject)
        ->flatMap(fn ($release) => $release->entries)
        ->keyBy('title');

    expect($withoutProjectEntries['Daily digest by email']->url)->toBe(route('notifications.edit'))
        ->and($withoutProjectEntries['MCP project kickoff']->url)->toBe(route('integrations.edit'))
        ->and($withoutProjectEntries['Account settings']->url)->toBe(route('profile.edit'))
        ->and($withoutProjectEntries['Projects overview']->url)->toBe(route('projects'))
        ->and($withoutProjectEntries['GitHub repository docs sync']->url)->toBeNull()
        ->and($withoutProjectEntries['Copy context for AI']->url)->toBeNull()
        ->and($withoutProjectEntries['Global quick capture']->url)->toBeNull()
        ->and($withoutProjectEntries['Project settings']->url)->toBeNull()
        ->and($withoutProjectEntries['Notebooks']->url)->toBeNull()
        ->and($withoutProjectEntries['Files']->url)->toBeNull()
        ->and($withoutProjectEntries['Project history']->url)->toBeNull();
});

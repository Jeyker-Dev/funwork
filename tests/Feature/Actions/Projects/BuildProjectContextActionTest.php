<?php

use App\Actions\Projects\BuildProjectContextAction;
use App\Enums\DecisionStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Decision;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('builds complete project context markdown with sections in order and excludes done and other users items', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'FunWork Hub',
        'description' => 'Central context hub for personal projects.',
        'type' => 'programming',
        'status' => 'active',
    ]);

    $backend = Area::factory()->create([
        'project_id' => $project->id,
        'name' => 'Backend',
        'slug' => 'backend',
        'position' => 1,
    ]);
    $frontend = Area::factory()->create([
        'project_id' => $project->id,
        'name' => 'Frontend',
        'slug' => 'frontend',
        'position' => 2,
    ]);

    $todoNoArea = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => null,
        'title' => 'Unassigned todo task',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'due_date' => null,
        'position' => 1,
    ]);
    $todoBackend = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'title' => 'Build brief action',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::High,
        'due_date' => '2026-12-01 00:00:00',
        'position' => 1,
    ]);
    $doingFrontend = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $frontend->id,
        'title' => 'Add copy button',
        'status' => TaskStatus::Doing,
        'priority' => TaskPriority::Medium,
        'due_date' => null,
        'position' => 1,
    ]);
    $doneTask = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'title' => 'Finished migration',
        'status' => TaskStatus::Done,
        'priority' => TaskPriority::High,
    ]);

    $acceptedDecision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Use Actions for business logic',
        'context' => 'Business rules need reuse across Livewire and MCP.',
        'decision' => 'Place invokable actions under app/Actions.',
        'consequences' => 'Entry points remain thin.',
    ]);
    $proposedDecision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 3,
        'title' => 'Add local LLM adapter',
        'context' => 'Future Brain chat requires provider abstraction.',
        'decision' => 'Introduce an AiProvider contract.',
        'consequences' => 'Easy to swap providers later.',
    ]);
    $supersededDecision = Decision::factory()->superseded($acceptedDecision)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Inline logic in Livewire',
        'context' => 'Legacy approach context should not appear.',
    ]);
    $rejectedDecision = Decision::factory()->rejected()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 4,
        'title' => 'Use GraphQL instead of MCP',
        'context' => 'Rejected context should not appear.',
        'decision' => 'Rejected decision should not appear.',
        'consequences' => 'Rejected consequences should not appear.',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'created_by' => $owner->id,
        'name' => 'Architecture Notes',
    ]);
    $longBody = str_repeat('Word ', 150).'TAIL_MARKER';
    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $owner->id,
        'order' => 1,
        'content' => [
            'title' => 'Overview',
            'body' => $longBody,
        ],
    ]);

    $olderInbox = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'content' => 'Older pending idea',
        'created_at' => now()->subHour(),
    ]);
    $newerInbox = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'content' => 'Newest pending idea',
        'created_at' => now(),
    ]);
    $deletedInbox = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'content' => 'Soft deleted idea',
    ]);
    $deletedInbox->delete();
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $otherUser->id,
        'content' => 'Secret other user idea',
    ]);

    $markdown = app(BuildProjectContextAction::class)($project, $owner);

    expect($markdown)
        ->toContain('# FunWork Hub')
        ->toContain('Central context hub for personal projects.')
        ->toContain('Type: programming')
        ->toContain('Status: active')
        ->toContain('## Areas')
        ->toContain('- Backend')
        ->toContain('- Frontend')
        ->toContain('## Open Tasks')
        ->toContain("Build brief action (Priority: High, Due: 2026-12-01, ID: {$todoBackend->id})")
        ->toContain("Unassigned todo task (Priority: Low, ID: {$todoNoArea->id})")
        ->toContain("Add copy button (Priority: Medium, ID: {$doingFrontend->id})")
        ->not->toContain($doneTask->title)
        ->toContain('## Decisions')
        ->toContain('### ADR-002 Use Actions for business logic')
        ->toContain('Status: Accepted')
        ->toContain('Business rules need reuse across Livewire and MCP.')
        ->toContain('Place invokable actions under app/Actions.')
        ->toContain('Entry points remain thin.')
        ->toContain('### ADR-003 Add local LLM adapter')
        ->toContain('Status: Proposed')
        ->toContain('Future Brain chat requires provider abstraction.')
        ->toContain('ADR-001 Inline logic in Livewire (superseded by ADR-002)')
        ->not->toContain('Legacy approach context should not appear.')
        ->toContain('- ADR-004 Use GraphQL instead of MCP (Rejected)')
        ->not->toContain('Rejected context should not appear.')
        ->not->toContain('Rejected decision should not appear.')
        ->not->toContain('Rejected consequences should not appear.')
        ->toContain('## Notebooks')
        ->toContain('### Architecture Notes')
        ->toContain('Area: Backend')
        ->toContain('#### Overview')
        ->toContain(Str::limit(trim($longBody), BuildProjectContextAction::MAX_NOTEBOOK_PAGE_BODY_LENGTH))
        ->not->toContain('TAIL_MARKER')
        ->toContain('## Inbox')
        ->toContain('- Newest pending idea')
        ->toContain('- Older pending idea')
        ->not->toContain($deletedInbox->content)
        ->not->toContain('Secret other user idea');

    $sectionPositions = [
        strpos($markdown, '# FunWork Hub'),
        strpos($markdown, '## Areas'),
        strpos($markdown, '## Open Tasks'),
        strpos($markdown, '### Todo'),
        strpos($markdown, '#### Backend'),
        strpos($markdown, '#### No area'),
        strpos($markdown, '### Doing'),
        strpos($markdown, '## Decisions'),
        strpos($markdown, '## Notebooks'),
        strpos($markdown, '## Inbox'),
        strpos($markdown, $newerInbox->content),
        strpos($markdown, $olderInbox->content),
    ];

    expect($sectionPositions)->not->toContain(false);

    $sortedPositions = $sectionPositions;
    sort($sortedPositions);

    expect($sectionPositions)->toBe($sortedPositions);
});

test('omits empty sections while keeping non-empty ones', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Lean Project',
        'type' => 'study',
        'status' => 'planning',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Read Laravel docs',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Medium,
        'due_date' => null,
    ]);

    $markdown = app(BuildProjectContextAction::class)($project, $owner);

    expect($markdown)
        ->toContain('# Lean Project')
        ->toContain('## Open Tasks')
        ->toContain('Read Laravel docs')
        ->not->toContain('## Areas')
        ->not->toContain('### Doing')
        ->not->toContain('## Decisions')
        ->not->toContain('## Notebooks')
        ->not->toContain('## Inbox')
        ->not->toContain('This project is empty.');
});

test('returns empty project message when project has no areas, open tasks, decisions, notebooks, or pending inbox items', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Blank Canvas',
        'description' => null,
        'type' => 'personal',
        'status' => 'active',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'title' => 'Already completed',
    ]);
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $otherUser->id,
        'content' => 'Another user note',
    ]);

    $markdown = app(BuildProjectContextAction::class)($project, $owner);

    expect($markdown)
        ->toContain('# Blank Canvas')
        ->toContain('This project is empty.')
        ->not->toContain('## Areas')
        ->not->toContain('## Open Tasks')
        ->not->toContain('## Decisions')
        ->not->toContain('## Notebooks')
        ->not->toContain('## Inbox')
        ->not->toContain('Already completed')
        ->not->toContain('Another user note');
});

test('query count stays constant regardless of project data volume', function () {
    $owner = User::factory()->create();
    $action = app(BuildProjectContextAction::class);

    $smallProject = Project::factory()->create(['owner_id' => $owner->id]);
    $smallArea = Area::factory()->create(['project_id' => $smallProject->id, 'position' => 1]);
    Task::factory()->create(['project_id' => $smallProject->id, 'area_id' => $smallArea->id, 'status' => TaskStatus::Todo]);
    $smallReplacement = Decision::factory()->accepted()->create(['project_id' => $smallProject->id, 'created_by' => $owner->id, 'number' => 2]);
    Decision::factory()->superseded($smallReplacement)->create(['project_id' => $smallProject->id, 'created_by' => $owner->id, 'number' => 1]);
    $smallNotebook = Notebook::factory()->create(['project_id' => $smallProject->id, 'area_id' => $smallArea->id, 'created_by' => $owner->id]);
    NotebookPage::factory()->create(['notebook_id' => $smallNotebook->id, 'created_by' => $owner->id]);
    InboxItem::factory()->create(['project_id' => $smallProject->id, 'user_id' => $owner->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $action($smallProject, $owner);
    $smallQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $largeProject = Project::factory()->create(['owner_id' => $owner->id]);
    $largeAreas = Area::factory()->count(6)->create(['project_id' => $largeProject->id]);
    foreach ($largeAreas as $area) {
        Task::factory()->count(4)->create([
            'project_id' => $largeProject->id,
            'area_id' => $area->id,
            'status' => TaskStatus::Todo,
        ]);
    }
    $largeReplacement = Decision::factory()->accepted()->create(['project_id' => $largeProject->id, 'created_by' => $owner->id, 'number' => 100]);
    for ($number = 1; $number <= 8; $number++) {
        Decision::factory()->create([
            'project_id' => $largeProject->id,
            'created_by' => $owner->id,
            'number' => $number,
            'status' => $number % 2 === 0 ? DecisionStatus::Superseded : DecisionStatus::Accepted,
            'superseded_by_id' => $number % 2 === 0 ? $largeReplacement->id : null,
        ]);
    }
    for ($i = 0; $i < 5; $i++) {
        $notebook = Notebook::factory()->create([
            'project_id' => $largeProject->id,
            'area_id' => $largeAreas[$i]->id,
            'created_by' => $owner->id,
        ]);
        NotebookPage::factory()->count(4)->create([
            'notebook_id' => $notebook->id,
            'created_by' => $owner->id,
        ]);
    }
    InboxItem::factory()->count(12)->create([
        'project_id' => $largeProject->id,
        'user_id' => $owner->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $action($largeProject, $owner);
    $largeQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeQueryCount)->toBe($smallQueryCount);
});

test('excludes archived notebooks from the project context', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $activeNotebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'Current Specs',
        'is_archived' => false,
    ]);
    NotebookPage::factory()->create([
        'notebook_id' => $activeNotebook->id,
        'created_by' => $owner->id,
        'content' => ['title' => 'Live page', 'body' => 'Still valid.'],
    ]);

    $archivedNotebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'Old Specs',
        'is_archived' => true,
    ]);
    NotebookPage::factory()->create([
        'notebook_id' => $archivedNotebook->id,
        'created_by' => $owner->id,
        'content' => ['title' => 'Outdated page', 'body' => 'No longer valid.'],
    ]);

    $markdown = app(BuildProjectContextAction::class)($project, $owner);

    expect($markdown)
        ->toContain('Current Specs')
        ->toContain('Live page')
        ->not->toContain('Old Specs')
        ->not->toContain('Outdated page');
});

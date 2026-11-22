<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('greeting uses the user first name and local time of day', function () {
    $this->travelTo(Carbon::parse('2026-11-23 14:00:00'));

    $user = User::factory()->create([
        'name' => 'Elena Vance',
        'timezone' => 'America/New_York',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.index')
        ->assertSee('Good morning, Elena');
});

test('summary counts active projects and tasks due this week accurately', function () {
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);

    $activeA = Project::factory()->create(['owner_id' => $user->id, 'status' => 'active']);
    $activeB = Project::factory()->create(['owner_id' => $user->id, 'status' => 'active']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => 'planning']);

    Task::factory()->count(3)->create([
        'project_id' => $activeA->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-26 00:00:00',
    ]);
    Task::factory()->create([
        'project_id' => $activeB->id,
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-26 00:00:00',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.index')
        ->assertSee('3 active projects, 3 tasks due this week');
});

test('summary counts active, planning, and on-hold projects as active while excluding done, completed, and archived', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);

    Project::factory()->create(['owner_id' => $user->id, 'status' => 'active']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => '  PLANNING ']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => 'on-hold']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => 'done']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => 'completed']);
    Project::factory()->create([
        'owner_id' => $user->id,
        'status' => 'active',
        'archived_at' => now()->subDay(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.index')
        ->assertSee('3 active projects');
});

test('continue card shows the most recently active project, progress, next task, and open button without arrow', function () {
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00'));

    $user = User::factory()->create();

    $olderProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Older Project',
        'updated_at' => now()->subDays(2),
    ]);
    $recentProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Recent Workspace',
        'type' => 'Programming',
        'due_at' => '2026-12-15 00:00:00',
        'updated_at' => now()->subHour(),
    ]);

    Task::factory()->create([
        'project_id' => $recentProject->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subHours(2),
    ]);
    Task::factory()->create([
        'project_id' => $recentProject->id,
        'title' => 'Ship redesigned projects list',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-28 00:00:00',
        'updated_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.index')
        ->assertSee('Continue')
        ->assertSee('Recent Workspace')
        ->assertSee('1 of 2 tasks done')
        ->assertSee('50%')
        ->assertSee('Next:')
        ->assertSee('Ship redesigned projects list')
        ->assertSee('Edited 30 minutes ago')
        ->assertSee('Open project')
        ->assertDontSee('All Your Projects,')
        ->assertSeeLivewire('projects.show-projects')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $openLinks = $xpath->query('//a[contains(normalize-space(.), "Open project")]');

    expect($openLinks->length)->toBe(1)
        ->and($xpath->query('.//svg', $openLinks->item(0))->length)->toBe(0);
});

test('continue card hides when search filter is active', function () {
    $user = User::factory()->create();
    Project::factory()->create(['owner_id' => $user->id, 'name' => 'Alpha Workspace']);
    Project::factory()->create(['owner_id' => $user->id, 'name' => 'Beta Workspace']);

    $this->actingAs($user);

    Livewire::test('pages::projects.index')
        ->assertSee('Continue')
        ->dispatch('search-updated', search: 'Alpha')
        ->assertDontSee('Continue');
});

test('empty state renders one message and a single create your first project action', function () {
    $user = User::factory()->create(['name' => 'Elena Vance']);

    $this->actingAs($user);

    Livewire::test('pages::projects.index')
        ->assertSee('0 active projects, 0 tasks due this week')
        ->assertSee('No projects yet')
        ->assertSee('Create your first project')
        ->assertDontSee('Continue')
        ->assertDontSee('All projects')
        ->assertSeeLivewire('projects.create-project');
});

test('reacts to project-created event by refreshing data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.index')
        ->assertSee('No projects yet')
        ->assertDontSee('Created Workspace');

    Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Created Workspace',
    ]);

    $component->dispatch('project-created')
        ->assertDontSee('No projects yet')
        ->assertSee('Continue')
        ->assertSee('Created Workspace');
});

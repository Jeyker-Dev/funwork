<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('the board filter by an area shows only its tasks, no area shows tasks without area, and counters match', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($project);

    $frontend = $project->areas()->where('slug', 'frontend')->first();
    $backend = $project->areas()->where('slug', 'backend')->first();

    // Create 2 frontend tasks (Todo), 1 backend task (Todo), 1 no-area task (Todo)
    $feTask1 = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Frontend Task 1',
        'status' => TaskStatus::Todo->value,
        'area_id' => $frontend->id,
        'position' => 1,
    ]);
    $feTask2 = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Frontend Task 2',
        'status' => TaskStatus::Doing->value,
        'area_id' => $frontend->id,
        'position' => 2,
    ]);
    $beTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Backend Task 1',
        'status' => TaskStatus::Todo->value,
        'area_id' => $backend->id,
        'position' => 3,
    ]);
    $noAreaTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'No Area Task 1',
        'status' => TaskStatus::Todo->value,
        'area_id' => null,
        'position' => 4,
    ]);

    // 1. Default (all): sees all tasks, column count = 4
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project])
        ->assertSee('Frontend Task 1')
        ->assertSee('Frontend Task 2')
        ->assertSee('Backend Task 1')
        ->assertSee('No Area Task 1');

    // 2. Filter by frontend: sees only frontend tasks, sees frontend badge, doesn't see backend or no-area task
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project])
        ->set('area', 'frontend')
        ->assertSee('Frontend Task 1')
        ->assertSee('Frontend Task 2')
        ->assertDontSee('Backend Task 1')
        ->assertDontSee('No Area Task 1');

    // 3. Filter by backend: sees only backend task
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project])
        ->set('area', 'backend')
        ->assertDontSee('Frontend Task 1')
        ->assertDontSee('Frontend Task 2')
        ->assertSee('Backend Task 1')
        ->assertDontSee('No Area Task 1');

    // 4. Filter by No area (e.g. 'no-area' or 'none')
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project])
        ->set('area', 'no-area')
        ->assertDontSee('Frontend Task 1')
        ->assertDontSee('Frontend Task 2')
        ->assertDontSee('Backend Task 1')
        ->assertSee('No Area Task 1');
});

test('slider filters and tasks respect the area filter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($project);

    $frontend = $project->areas()->where('slug', 'frontend')->first();
    $backend = $project->areas()->where('slug', 'backend')->first();

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'FE Slider Task',
        'status' => TaskStatus::Todo->value,
        'area_id' => $frontend->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'BE Slider Task',
        'status' => TaskStatus::Todo->value,
        'area_id' => $backend->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'None Slider Task',
        'status' => TaskStatus::Todo->value,
        'area_id' => null,
    ]);

    // slider-tasks with frontend filter
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡slider-tasks', ['project' => $project, 'area' => 'frontend'])
        ->assertSee('FE Slider Task')
        ->assertDontSee('BE Slider Task')
        ->assertDontSee('None Slider Task');

    // slider-tasks with no-area filter
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡slider-tasks', ['project' => $project, 'area' => 'no-area'])
        ->assertDontSee('FE Slider Task')
        ->assertDontSee('BE Slider Task')
        ->assertSee('None Slider Task');

    // slider-filters with frontend filter
    $sliderFilters = Livewire::actingAs($user)
        ->test('projects.tasks.⚡slider-filters', ['project' => $project, 'area' => 'frontend']);

    expect($sliderFilters->viewData('counts')['all'])->toBe(1)
        ->and($sliderFilters->viewData('counts')[TaskStatus::Todo->value])->toBe(1);
});

test('task-count and task-more-indicator respect the area filter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($project);

    $frontend = $project->areas()->where('slug', 'frontend')->first();
    $backend = $project->areas()->where('slug', 'backend')->first();

    for ($i = 1; $i <= 5; $i++) {
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'area_id' => $frontend->id,
            'title' => "FE Task {$i}",
        ]);
    }

    for ($i = 1; $i <= 2; $i++) {
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'area_id' => $backend->id,
            'title' => "BE Task {$i}",
        ]);
    }

    // Task count for FE: should be 5
    $feCount = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-count', ['project' => $project, 'status' => TaskStatus::Todo->value, 'area' => 'frontend']);
    expect($feCount->viewData('count'))->toBe(5);

    // Task count for BE: should be 2
    $beCount = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-count', ['project' => $project, 'status' => TaskStatus::Todo->value, 'area' => 'backend']);
    expect($beCount->viewData('count'))->toBe(2);

    // Task more indicator for FE: 5 - 3 = 2 hidden
    $feIndicator = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-more-indicator', ['project' => $project, 'status' => TaskStatus::Todo->value, 'area' => 'frontend']);
    expect($feIndicator->viewData('hiddenTaskCount'))->toBe(2);

    // Task more indicator for BE: 2 <= 3 -> 0 hidden
    $beIndicator = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-more-indicator', ['project' => $project, 'status' => TaskStatus::Todo->value, 'area' => 'backend']);
    expect($beIndicator->viewData('hiddenTaskCount'))->toBe(0);
});

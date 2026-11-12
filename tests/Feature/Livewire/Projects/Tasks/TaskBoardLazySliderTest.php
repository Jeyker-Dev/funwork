<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('rendering the task board with hidden tasks does not mount or query the slider components', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    for ($i = 1; $i <= 5; $i++) {
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'title' => "Board-Todo-Task-{$i}",
            'position' => $i,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $component = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project]);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $component->assertOk();

    // The first 3 tasks are visible on the board column
    $component->assertSee('Board-Todo-Task-1');
    $component->assertSee('Board-Todo-Task-2');
    $component->assertSee('Board-Todo-Task-3');
    $component->assertSee('+ 2 more tasks ...');

    // Hidden tasks from the slider must NOT be in the initial HTML
    $component->assertDontSee('Board-Todo-Task-4');
    $component->assertDontSee('Board-Todo-Task-5');

    // The slider tasks container must not be rendered in the initial DOM
    $html = $component->html();
    expect($html)->not->toContain('<div class="grid grid-cols-2 gap-7 max-w-lg">')
        ->and($html)->toContain('x-intersect');

    // Ensure none of the queries originated from slider components (which paginate with limit 6)
    foreach ($queries as $query) {
        expect($query['query'])->not->toContain('limit 6');
    }
});

test('rendering task-more-indicator with hidden tasks does not mount or query the slider components', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    for ($i = 1; $i <= 5; $i++) {
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'title' => "Indicator-Todo-Task-{$i}",
            'position' => $i,
        ]);
    }

    $component = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-more-indicator', [
            'project' => $project,
            'status' => TaskStatus::Todo->value,
        ]);

    $component->assertOk();
    $component->assertSee('+ 2 more tasks ...');

    // Hidden tasks are not rendered in the initial HTML
    $component->assertDontSee('Indicator-Todo-Task-4');
    $component->assertDontSee('Indicator-Todo-Task-5');

    $html = $component->html();
    expect($html)->not->toContain('<div class="grid grid-cols-2 gap-7 max-w-lg">')
        ->and($html)->toContain('x-intersect');
});

test('the slider component still renders its tasks when mounted directly', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    for ($i = 1; $i <= 5; $i++) {
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'title' => "Direct-Slider-Task-{$i}",
            'position' => $i,
        ]);
    }

    $component = Livewire::actingAs($user)
        ->test('projects.tasks.⚡slider-tasks', [
            'project' => $project,
            'initialFilter' => TaskStatus::Todo->value,
        ]);

    $component->assertOk();

    for ($i = 1; $i <= 5; $i++) {
        $component->assertSee("Direct-Slider-Task-{$i}");
    }
});

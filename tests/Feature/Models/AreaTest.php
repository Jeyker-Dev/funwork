<?php

use App\Models\Area;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('an area belongs to a project', function () {
    $area = Area::factory()->create();

    expect($area->project)->toBeInstanceOf(Project::class);
});

test('an area has many tasks', function () {
    $area = Area::factory()->create();
    $task = Task::factory()->create([
        'project_id' => $area->project_id,
        'area_id' => $area->id,
    ]);

    expect($area->tasks)->toHaveCount(1)
        ->and($area->tasks->first()->id)->toBe($task->id)
        ->and($task->area->id)->toBe($area->id);
});

test('an area has many notebooks', function () {
    $user = User::factory()->create();
    $area = Area::factory()->create();
    $notebook = Notebook::factory()->create([
        'project_id' => $area->project_id,
        'created_by' => $user->id,
        'area_id' => $area->id,
    ]);

    expect($area->notebooks)->toHaveCount(1)
        ->and($area->notebooks->first()->id)->toBe($notebook->id)
        ->and($notebook->area->id)->toBe($area->id);
});

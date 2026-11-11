<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('task board card renders plain text excerpt clamped to 2 lines without markdown or html tokens', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $markdown = <<<'MD'
    # Big Title
    This is **bold** text and `inline code`.
    ```php
    echo "test";
    ```
    MD;

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Card Markdown Task',
        'description' => $markdown,
    ]);

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project])
        ->assertOk()
        ->assertSee('Card Markdown Task')
        ->assertSee($task->description_excerpt)
        ->assertSeeHtml('line-clamp-2')
        ->assertDontSeeHtml('<h1>Big Title</h1>')
        ->assertDontSeeHtml('<strong>bold</strong>')
        ->assertDontSeeHtml('<pre>')
        ->assertDontSeeHtml('<code>inline code</code>');
});

test('task list card renders plain text excerpt clamped to 2 lines without markdown or html tokens', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $markdown = <<<'MD'
    # Another Title
    Details with **critical info** and a [link](https://example.com).
    MD;

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'List Markdown Task',
        'description' => $markdown,
    ]);

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡show-tasks', ['project' => $project])
        ->assertOk()
        ->assertSee('List Markdown Task')
        ->assertSee($task->description_excerpt)
        ->assertSeeHtml('line-clamp-2')
        ->assertDontSeeHtml('<h1>Another Title</h1>')
        ->assertDontSeeHtml('<strong>critical info</strong>');
});

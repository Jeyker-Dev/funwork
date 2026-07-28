<?php

use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

it('converts an inbox item to a task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $item = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Do the thing',
    ]);

    Livewire::actingAs($user)
        ->test('projects.inbox.convert-to-task')
        ->call('loadModal', $item->id)
        ->set('form.projectId', $project->id)
        ->set('form.title', 'Do the thing')
        ->set('form.description', 'Need to do it soon')
        ->call('convert')
        ->assertRedirect(route('project.tasks', $project->id));

    expect(Task::where('project_id', $project->id)->count())->toBe(1);
    expect(InboxItem::where('id', $item->id)->whereNull('deleted_at')->count())->toBe(0);
});

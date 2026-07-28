<?php

use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

it('converts an inbox item to a notebook', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $item = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Some notes',
    ]);

    Livewire::actingAs($user)
        ->test('projects.inbox.convert-to-notebook')
        ->call('loadModal', $item->id)
        ->set('form.projectId', $project->id)
        ->set('form.name', 'Note from Inbox')
        ->set('form.description', 'Some notes')
        ->call('convert')
        ->assertRedirect(route('project.notebooks', $project->id));

    expect(Notebook::where('project_id', $project->id)->count())->toBe(1);
    expect(InboxItem::where('id', $item->id)->whereNull('deleted_at')->count())->toBe(0);
});

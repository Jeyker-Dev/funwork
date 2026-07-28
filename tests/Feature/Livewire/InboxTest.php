<?php

use App\Models\InboxItem;
use App\Models\User;
use App\Models\Project;
use Livewire\Livewire;

it('renders inbox component', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('project.inbox', $project))
        ->assertSee('Inbox');
});

it('lists inbox items', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    InboxItem::factory()->count(3)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee($user->inboxItems->first()->content);
});

it('can add an item to inbox', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->set('form.content', 'New Idea')
        ->call('addItem')
        ->assertHasNoErrors();

    expect($user->inboxItems()->count())->toBe(1);
    expect($user->inboxItems()->first()->project_id)->toBe($project->id);
    expect($user->inboxItems()->first()->content)->toBe('New Idea');
});

it('can delete an item', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $item = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    Livewire::actingAs($user)
        ->test('projects.inbox.delete-item-modal')
        ->call('loadModal', $item->id)
        ->call('delete')
        ->assertHasNoErrors()
        ->assertDispatched('item-deleted');

    expect($user->inboxItems()->count())->toBe(0);
});

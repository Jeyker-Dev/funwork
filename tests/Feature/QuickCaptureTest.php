<?php

use App\Models\Project;
use App\Models\User;
use App\Models\InboxItem;
use Livewire\Livewire;

test('quick capture form saves inbox item and resets', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::projects.show', ['project' => $project])
        ->set('captureForm.content', 'Test quick idea')
        ->call('saveQuickCapture');

    $response->assertHasNoErrors()
             ->assertSet('captureForm.content', '');

    expect(InboxItem::count())->toBe(1);
    
    $item = InboxItem::first();
    expect($item->content)->toBe('Test quick idea');
    expect($item->project_id)->toBe($project->id);
    expect($item->user_id)->toBe($user->id);
});

test('quick capture form requires content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::projects.show', ['project' => $project])
        ->set('captureForm.content', '')
        ->call('saveQuickCapture');

    $response->assertHasErrors(['captureForm.content' => 'required']);
});

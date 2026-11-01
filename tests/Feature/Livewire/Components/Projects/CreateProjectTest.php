<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders header trigger button when elementName is header', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => 'header'])
        ->assertSee('New project')
        ->assertDontSee('Create your first project');
});

test('renders default empty state trigger when elementName is empty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->assertSee('Create your first project')
        ->assertDontSee('New project');
});

test('fails validation for required fields', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->call('save')
        ->assertHasErrors([
            'form.name' => 'required',
            'form.description' => 'required',
            'form.icon' => 'required',
            'form.type' => 'required',
            'form.status' => 'required',
            'form.ownerId' => 'required',
        ]);

    $this->assertDatabaseCount('projects', 0);
});

test('fails validation for invalid date formats', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->set('form.dueDate', 'invalid-date')
        ->set('form.archiveDate', 'invalid-date')
        ->call('save')
        ->assertHasErrors([
            'form.dueDate' => 'date_format',
            'form.archiveDate' => 'date_format',
        ]);

    $this->assertDatabaseCount('projects', 0);
});

test('successfully creates a project', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->set('form.name', 'Test Project')
        ->set('form.description', 'Test Description')
        ->set('form.icon', 'wrench')
        ->set('form.type', 'Programming')
        ->set('form.status', 'active')
        ->set('form.ownerId', $user->id)
        ->set('form.dueDate', '2025-12-31')
        ->set('form.archiveDate', '2026-01-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('project-created');

    $this->assertDatabaseHas('projects', [
        'name' => 'Test Project',
        'description' => 'Test Description',
        'icon' => 'wrench',
        'type' => 'Programming',
        'status' => 'active',
        'owner_id' => $user->id,
        'due_at' => '2025-12-31',
        'archived_at' => '2026-01-01',
    ]);
});

test('only offers the authenticated user in the project owner selector', function () {
    $user = User::factory()->create(['username' => 'authenticated_owner']);
    $otherUser = User::factory()->create(['username' => 'other_foreign_user']);

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->assertSee('authenticated_owner')
        ->assertDontSee('other_foreign_user');
});

test('aborts with 403 when attempting to create a project with another user as owner', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->set('form.name', 'Spoofed Project')
        ->set('form.description', 'Test Description')
        ->set('form.icon', 'wrench')
        ->set('form.type', 'Programming')
        ->set('form.status', 'active')
        ->set('form.ownerId', $otherUser->id)
        ->call('save')
        ->assertForbidden();

    $this->assertDatabaseMissing('projects', [
        'name' => 'Spoofed Project',
    ]);
});

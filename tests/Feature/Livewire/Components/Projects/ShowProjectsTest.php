<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders project cards with icon, name, description, type tag, and status label', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $project = Project::factory()->create([
        'name' => 'Awesome Project',
        'description' => 'A very awesome project.',
        'type' => 'Programming',
        'status' => 'active',
        'due_at' => Carbon::parse('2026-12-10'),
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('All projects')
        ->assertSee('Awesome Project')
        ->assertSee('A very awesome project.')
        ->assertSee('Programming')
        ->assertSee('Active')
        ->assertSee('Dec 10, 2026')
        ->assertDontSee('line-card')
        ->assertDontSee('hover:translate-y-2');
});

test('owner email and owner name are no longer rendered on project cards', function () {
    $user = User::factory()->create([
        'name' => 'Secret Owner Name',
        'username' => 'secret_owner_handle',
        'email' => 'secret-owner@example.com',
    ]);
    Project::factory()->create([
        'name' => 'Client Portal',
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('Client Portal')
        ->assertDontSee('secret-owner@example.com')
        ->assertDontSee('secret_owner_handle')
        ->assertDontSee('Secret Owner Name');
});

test('card progress shows done over total tasks without a redundant Progress label', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Editorial Refresh',
        'owner_id' => $user->id,
    ]);

    Task::factory()->count(2)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
    ]);
    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('projects.show-projects')
        ->assertSee('2 of 5 tasks')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $gridNodes = $xpath->query('//*[@data-projects-grid]');

    expect($gridNodes->length)->toBe(1)
        ->and($gridNodes->item(0)?->textContent)->not->toContain('Progress');
});

test('type tags do not use brand orange classes', function () {
    $user = User::factory()->create();
    Project::factory()->create([
        'name' => 'Compiler Project',
        'type' => 'Programming',
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('projects.show-projects')->html();

    preg_match_all('/data-project-type-tag[^>]*class="([^"]*)"/', $html, $matches);
    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $classList) {
        expect($classList)
            ->not->toContain('orange')
            ->not->toContain('brand');
    }
});

test('filters projects by search property', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Project')
        ->assertDontSee('Beta Project');
});

test('reacts to search-updated event', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->dispatch('search-updated', search: 'Beta')
        ->assertSet('search', 'Beta')
        ->assertSee('Beta Project')
        ->assertDontSee('Alpha Project');
});

test('reacts to project-created event by refreshing data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('projects.show-projects')
        ->assertDontSee('New Dynamic Project');

    Project::factory()->create([
        'name' => 'New Dynamic Project',
        'owner_id' => $user->id,
    ]);

    $component->dispatch('project-created')
        ->assertSee('New Dynamic Project');
});

test('searching for percent wildcard returns only project with literal percent and not every project', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Project 100%', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Other Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', '%')
        ->assertSee('Project 100%')
        ->assertDontSee('Other Project');
});

test('searching for underscore wildcard matches only a project whose name literally contains underscore', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'project_alpha', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'projectalpha', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', '_')
        ->assertSee('project_alpha')
        ->assertDontSee('projectalpha');
});

test('regression: searching for normal text continues to return matching projects', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Project')
        ->assertDontSee('Beta Project');
});

test('never shows other users projects in the list or search results', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Project::factory()->create(['name' => 'My Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Foreign Project', 'owner_id' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('My Project')
        ->assertDontSee('Foreign Project')
        ->set('search', 'Foreign')
        ->assertDontSee('Foreign Project');
});

test('links each project card to its dashboard and omits due date when unset', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Undated Project',
        'due_at' => null,
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('Undated Project')
        ->assertSeeHtml(route('projects.show', ['project' => $project]))
        ->assertDontSee('Due:');
});

test('renders project list in constant query count without N+1 queries', function () {
    $user = User::factory()->create();

    Project::factory()->count(10)->create(['owner_id' => $user->id])->each(function (Project $project): void {
        Task::factory()->count(3)->create(['project_id' => $project->id]);
    });

    $this->actingAs($user);

    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test('projects.show-projects');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(2);
});

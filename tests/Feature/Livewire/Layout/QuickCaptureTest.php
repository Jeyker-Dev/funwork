<?php

use App\Http\Middleware\RememberLastVisitedProject;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves an idea to the current project inbox and dispatches quick-capture-saved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Current Project',
    ]);

    $component = Livewire::actingAs($user)
        ->test('layout.quick-capture', ['project' => $project])
        ->assertSee('Current Project')
        ->assertSee('↵ Enter')
        ->set('form.content', 'Capture from current project')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.content', '')
        ->assertDispatched('quick-capture-saved');

    $item = InboxItem::query()->first();

    expect($item)->not->toBeNull()
        ->and($item->project_id)->toBe($project->id)
        ->and($item->user_id)->toBe($user->id)
        ->and($item->content)->toBe('Capture from current project')
        ->and($component->html())->toContain('<kbd');
});

it('remembers the last visited project in the session and saves to it outside a project', function () {
    $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $visitedProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Visited Earlier',
        'updated_at' => now()->subDays(2),
    ]);
    Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'More Recent Activity',
        'updated_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', $visitedProject))
        ->assertSuccessful()
        ->assertSessionHas(RememberLastVisitedProject::SESSION_KEY, $visitedProject->id);

    Livewire::actingAs($user)
        ->test('layout.quick-capture')
        ->assertSee('Visited Earlier')
        ->set('form.content', 'Outside project idea')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('quick-capture-saved');

    $item = InboxItem::query()->first();

    expect($item)->not->toBeNull()
        ->and($item->project_id)->toBe($visitedProject->id)
        ->and($item->content)->toBe('Outside project idea');
});

it('falls back to the most recently active project when there is no session value or it is unauthorized', function () {
    $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Older Project',
        'updated_at' => now()->subDays(3),
    ]);
    $recentProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Most Active Project',
        'updated_at' => now()->subMinutes(10),
    ]);
    $unauthorizedProject = Project::factory()->create([
        'owner_id' => $otherUser->id,
        'name' => 'Forbidden Project',
    ]);

    Livewire::actingAs($user)
        ->test('layout.quick-capture')
        ->assertSee('Most Active Project')
        ->set('form.content', 'Fallback without session')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('quick-capture-saved');

    expect(InboxItem::query()->latest('id')->first()?->project_id)->toBe($recentProject->id);

    session([RememberLastVisitedProject::SESSION_KEY => $unauthorizedProject->id]);

    Livewire::actingAs($user)
        ->test('layout.quick-capture')
        ->assertSee('Most Active Project')
        ->assertDontSee('Forbidden Project')
        ->set('form.content', 'Fallback from unauthorized session')
        ->call('save')
        ->assertHasNoErrors();

    expect(InboxItem::query()->latest('id')->first()?->project_id)->toBe($recentProject->id);
});

it('disables the input with a short message when the user has no projects', function () {
    $user = User::factory()->create();

    $html = Livewire::actingAs($user)
        ->test('layout.quick-capture')
        ->assertSee('No projects yet. Create a project first to capture ideas.')
        ->set('form.content', 'Should not save')
        ->call('save')
        ->html();

    expect(InboxItem::query()->count())->toBe(0)
        ->and($html)->toContain('disabled');
});

it('rejects another users project with 403', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Project::factory()->create(['owner_id' => $user->id]);
    $otherProject = Project::factory()->create(['owner_id' => $otherUser->id]);

    Livewire::actingAs($user)
        ->test('layout.quick-capture', ['project' => $otherProject])
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test('layout.quick-capture')
        ->set('form.projectId', $otherProject->id)
        ->set('form.content', 'Unauthorized capture')
        ->call('save')
        ->assertForbidden();

    expect(InboxItem::query()->count())->toBe(0);
});

it('rejects empty content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('layout.quick-capture', ['project' => $project])
        ->set('form.content', '')
        ->call('save')
        ->assertHasErrors(['form.content' => 'required']);

    expect(InboxItem::query()->count())->toBe(0);
});

it('renders on non-project pages and refreshes an open inbox page on quick-capture-saved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Alpha Workspace',
    ]);

    $this->actingAs($user)
        ->get(route('whats-new'))
        ->assertSuccessful()
        ->assertSeeLivewire('layout.quick-capture');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertSeeLivewire('layout.quick-capture');

    $this->actingAs($user)
        ->get(route('projects'))
        ->assertSuccessful()
        ->assertSeeLivewire('layout.quick-capture');

    $inboxPage = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('0 to process');

    InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Captured via global shortcut',
    ]);

    $inboxPage->dispatch('quick-capture-saved')
        ->assertSee('1 to process')
        ->assertSee('Captured via global shortcut');
});

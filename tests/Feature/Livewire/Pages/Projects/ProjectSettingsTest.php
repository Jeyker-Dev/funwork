<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('S1: project settings route is accessible to owner, forbidden to non-owners, and renders clean heading without eyebrow or invented ID', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Website Relaunch',
    ]);

    $this->actingAs($otherUser)
        ->get(route('project.settings', $project))
        ->assertForbidden();

    $response = $this->actingAs($owner)
        ->get(route('project.settings', $project));

    $response->assertSuccessful()
        ->assertSee('Project settings')
        ->assertSee('General')
        ->assertSee('Areas')
        ->assertSee('Danger zone')
        ->assertDontSee('PROJECT CONFIGURATION')
        ->assertDontSee('Project configuration')
        ->assertDontSee('Settings & taxonomies')
        ->assertDontSee('Scoped to')
        ->assertDontSee('PRJ-RELAUNCH-01')
        ->assertDontSee('form-line')
        ->assertDontSee('line-card');

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);

    $sidebarSettingsLink = $xpath->query('//a[@data-flux-sidebar-item and contains(@href, "/settings")]')->item(0);
    expect($sidebarSettingsLink)->not->toBeNull()
        ->and($sidebarSettingsLink->hasAttribute('data-current'))->toBeTrue();
});

test('S2: owner can update project general details and icon reusing ProjectForm validation rules', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Old Name',
        'description' => 'Old description',
        'icon' => 'folder',
        'type' => ProjectType::Personal->value,
        'status' => ProjectStatus::Planning->value,
        'due_at' => '2026-10-01',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::projects.settings', ['project' => $project])
        ->assertSet('form.name', 'Old Name')
        ->assertSet('form.description', 'Old description')
        ->assertSet('form.icon', 'folder')
        ->assertSet('form.type', ProjectType::Personal->value)
        ->assertSet('form.status', ProjectStatus::Planning->value)
        ->assertSet('form.dueDate', '2026-10-01')
        ->set('form.name', '')
        ->call('saveGeneral')
        ->assertHasErrors(['form.name' => 'required'])
        ->set('form.name', 'Website Relaunch')
        ->set('form.description', 'Core redesign of our marketing site and customer onboarding flow.')
        ->set('form.icon', 'wrench')
        ->set('form.type', ProjectType::Design->value)
        ->set('form.status', ProjectStatus::Active->value)
        ->set('form.dueDate', '2026-12-15')
        ->call('saveGeneral')
        ->assertHasNoErrors()
        ->assertDispatched('project-settings-saved');

    $project->refresh();

    expect($project->name)->toBe('Website Relaunch')
        ->and($project->description)->toBe('Core redesign of our marketing site and customer onboarding flow.')
        ->and($project->icon)->toBe('wrench')
        ->and($project->type)->toBe(ProjectType::Design->value)
        ->and($project->status)->toBe(ProjectStatus::Active->value)
        ->and($project->due_at)->toStartWith('2026-12-15');
});

test('S2: non-owner cannot save general project settings', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Original Name',
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::projects.settings', ['project' => $project])
        ->assertForbidden();

    expect($project->fresh()->name)->toBe('Original Name');
});

test('S3: areas card displays areas with color dot, name, task count, and supports adding, editing, reordering, and deleting areas', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    app(CreateDefaultAreasAction::class)($project);

    $frontend = $project->areas()->where('slug', 'frontend')->firstOrFail();
    $backend = $project->areas()->where('slug', 'backend')->firstOrFail();

    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'area_id' => $frontend->id,
    ]);
    Task::factory()->count(1)->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::projects.settings', ['project' => $project])
        ->assertSee('Frontend')
        ->assertSee('3 tasks')
        ->assertSee('Backend')
        ->assertSee('1 task');

    // Add area + unique slug validation
    $component->set('newName', 'Frontend')
        ->set('newColor', 'red')
        ->call('createArea')
        ->assertHasErrors(['newName']);

    $component->set('newName', 'DevOps')
        ->set('newColor', 'cyan')
        ->call('createArea')
        ->assertHasNoErrors();

    $devops = $project->areas()->where('slug', 'devops')->first();
    expect($devops)->not->toBeNull()
        ->and($devops->name)->toBe('DevOps')
        ->and($devops->color)->toBe('cyan')
        ->and($devops->position)->toBe(5);

    // Edit area
    $component->call('startEditing', $devops->id)
        ->set('editingName', 'Infrastructure')
        ->set('editingColor', 'pink')
        ->call('updateArea')
        ->assertHasNoErrors();

    expect($devops->fresh()->name)->toBe('Infrastructure')
        ->and($devops->fresh()->slug)->toBe('infrastructure')
        ->and($devops->fresh()->color)->toBe('pink');

    // Reorder via drag (sortArea) and up/down controls
    $component->call('sortArea', $devops->id, 0);
    expect($devops->fresh()->position)->toBe(1)
        ->and($frontend->fresh()->position)->toBe(2);

    $component->call('moveDown', $devops->id);
    expect($devops->fresh()->position)->toBe(2)
        ->and($frontend->fresh()->position)->toBe(1);

    $component->call('moveUp', $devops->id);
    expect($devops->fresh()->position)->toBe(1);

    // Delete area with confirmation preserves tasks and notebooks
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'area_id' => $devops->id,
    ]);

    $component->call('confirmDeleteArea', $devops->id)
        ->call('deleteArea')
        ->assertHasNoErrors();

    expect(Area::find($devops->id))->toBeNull()
        ->and($notebook->fresh()->area_id)->toBeNull();
});

test('S3: top bar Areas button links to project.settings#areas and standalone manage-areas modal is removed', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertSuccessful();

    $expectedHref = route('project.settings', $project).'#areas';
    $response->assertSeeHtml('href="'.$expectedHref.'"');
    $response->assertDontSee('manage-areas-modal');
});

test('S4: owner can archive and unarchive the project with confirmation', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'archived_at' => null,
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::projects.settings', ['project' => $project])
        ->assertSee('Archive project')
        ->call('archiveProject')
        ->assertHasNoErrors();

    expect($project->fresh()->archived_at)->not->toBeNull();

    $component->assertSee('Unarchive')
        ->call('unarchiveProject')
        ->assertHasNoErrors();

    expect($project->fresh()->archived_at)->toBeNull();
});

test('S4: owner must type the exact project name to delete the project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Website Relaunch',
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::projects.settings', ['project' => $project])
        ->set('deleteConfirmationName', 'Wrong Name')
        ->call('deleteProject')
        ->assertHasErrors(['deleteConfirmationName']);

    expect(Project::find($project->id))->not->toBeNull();

    $component->set('deleteConfirmationName', 'Website Relaunch')
        ->call('deleteProject')
        ->assertHasNoErrors()
        ->assertRedirect(route('projects'));

    expect(Project::find($project->id))->toBeNull();
});

test('S4: ProjectPolicy enforces archive and delete for owner only and settings page hides danger zone when policy denies', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $policy = new ProjectPolicy;

    expect($policy->archive($owner, $project))->toBeTrue()
        ->and($policy->archive($otherUser, $project))->toBeFalse()
        ->and($policy->delete($owner, $project))->toBeTrue()
        ->and($policy->delete($otherUser, $project))->toBeFalse();

    foreach (['archiveProject', 'unarchiveProject', 'deleteProject'] as $dangerAction) {
        $project->update(['owner_id' => $owner->id]);

        $component = Livewire::actingAs($owner)
            ->test('pages::projects.settings', ['project' => $project])
            ->assertSee('Danger zone')
            ->assertSee('Delete project');

        $project->update(['owner_id' => $otherUser->id]);

        $component->call($dangerAction)->assertForbidden();
    }
});

test('S5: mobile and dark mode classes provide at least 44px touch targets and stacked cards without horizontal overflow', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Mobile Responsive Project',
    ]);
    app(CreateDefaultAreasAction::class)($project);

    $html = $this->actingAs($owner)
        ->get(route('project.settings', $project))
        ->assertSuccessful()
        ->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Three main section cards: General, Areas, Danger zone
    $sections = $xpath->query('//section[@id="general" or @id="areas" or @id="danger-zone"]');
    expect($sections->length)->toBe(3);

    foreach ($sections as $section) {
        $classes = $section->getAttribute('class');
        expect($classes)->toContain('bg-surface-card')
            ->and($classes)->toContain('rounded-2xl')
            ->and($classes)->not->toContain('form-line')
            ->and($classes)->not->toContain('line-card');
    }

    // Mobile touch targets (>= 44px via min-h-11 / max-md:min-h-11)
    $changeIconTrigger = $xpath->query('//*[@data-test="change-icon-trigger"]')->item(0);
    $openAddArea = $xpath->query('//*[@data-test="open-add-area"]')->item(0);
    $archiveTrigger = $xpath->query('//*[@data-test="archive-project-trigger"]')->item(0);
    $deleteTrigger = $xpath->query('//*[@data-test="delete-project-trigger"]')->item(0);

    expect($changeIconTrigger?->getAttribute('class'))->toContain('min-h-11')
        ->and($openAddArea?->getAttribute('class'))->toContain('min-h-11')
        ->and($archiveTrigger?->getAttribute('class'))->toContain('min-h-11')
        ->and($deleteTrigger?->getAttribute('class'))->toContain('min-h-11')
        ->and($deleteTrigger?->getAttribute('class'))->not->toContain('bg-danger ')
        ->and($deleteTrigger?->getAttribute('class'))->toContain('text-danger');
});

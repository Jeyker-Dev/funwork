<?php

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('top bar renders the project name once inside a pill switcher on the left and places areas, notifications, and user menu on the right', function () {
    $user = User::factory()->create(['name' => 'Alex Rivera']);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Website Relaunch',
        'icon' => 'wrench',
    ]);

    $response = $this->actingAs($user)
        ->get(route('projects.show', $project));

    $response->assertSuccessful();

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);

    $header = $xpath->query('//header[@data-flux-header]')->item(0);
    expect($header)->not->toBeNull();

    $headerText = $header->textContent;
    expect(substr_count($headerText, 'Website Relaunch'))->toBe(1);

    $switcher = $xpath->query('.//*[@data-project-switcher]', $header)->item(0);
    expect($switcher)->not->toBeNull()
        ->and($switcher->textContent)->toContain('Website Relaunch');

    $rightControls = $xpath->query('.//*[@data-header-right]', $header)->item(0);
    expect($rightControls)->not->toBeNull()
        ->and($rightControls->textContent)->toContain('Areas')
        ->and($rightControls->textContent)->toContain('Alex');
});

test('all project screens render the updated sidebar and top bar without missing routes', function (string $routeName) {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Alpha Launch',
    ]);

    $response = $this->actingAs($user)
        ->get(route($routeName, $project));

    $response->assertSuccessful();

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);

    $header = $xpath->query('//header[@data-flux-header]')->item(0);
    expect($header)->not->toBeNull()
        ->and(substr_count($header->textContent, 'Alpha Launch'))->toBe(1);

    $sidebarLinks = $xpath->query('//a[@data-flux-sidebar-item]');
    expect($sidebarLinks->length)->toBe(9);
})->with([
    'home' => 'projects.show',
    'inbox' => 'project.inbox',
    'tasks' => 'project.tasks',
    'notebooks' => 'project.notebooks',
    'decisions' => 'project.decisions',
    'history' => 'project.history',
    'files' => 'project.files',
    'settings' => 'project.settings',
]);

test('top bar preserves search input on non-home project routes while hiding it on project home', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $homeHtml = $this->actingAs($user)->get(route('projects.show', $project))->assertSuccessful()->getContent();
    $tasksHtml = $this->actingAs($user)->get(route('project.tasks', $project))->assertSuccessful()->getContent();

    expect($homeHtml)->not->toContain('Search tasks')
        ->and($tasksHtml)->toContain('Search tasks');
});

test('project switcher allows truncation inside the flex header on narrow mobile viewports', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Very Long Project Name That Should Truncate Cleanly On Mobile',
    ]);

    $response = $this->actingAs($user)
        ->get(route('project.decisions', $project));

    $response->assertSuccessful();

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);

    $header = $xpath->query('//header[@data-flux-header]')->item(0);
    $leftGroup = $xpath->query('.//*[@data-header-left]', $header)->item(0);
    $switcher = $xpath->query('.//*[@data-project-switcher]', $header)->item(0);

    expect($leftGroup)->not->toBeNull()
        ->and($leftGroup->getAttribute('class'))->toContain('flex-1')
        ->and($leftGroup->getAttribute('class'))->toContain('min-w-0')
        ->and($switcher)->not->toBeNull()
        ->and($switcher->getAttribute('class'))->toContain('min-w-0')
        ->and($switcher->getAttribute('class'))->toContain('max-w-full');
});

test('top bar search renders on tasks and is hidden on notebooks, notebook editor, decisions, and history', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);
    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $user->id,
        'order' => 1,
    ]);

    $tasksHtml = $this->actingAs($user)->get(route('project.tasks', $project))->assertSuccessful()->getContent();
    expect($tasksHtml)->toContain('Search tasks')
        ->and($tasksHtml)->toContain('aria-label="Search"');

    $urlsWithoutTopSearch = [
        route('project.notebooks', $project),
        route('project.notebooks.pages.edit', [$project, $notebook, $page]),
        route('project.decisions', $project),
        route('project.history', $project),
        route('project.inbox', $project),
        route('project.files', $project),
        route('project.settings', $project),
        route('projects.show', $project),
    ];

    foreach ($urlsWithoutTopSearch as $url) {
        $html = $this->actingAs($user)->get($url)->assertSuccessful()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $header = $xpath->query('//header[@data-flux-header]')->item(0);
        expect($header)->not->toBeNull();

        $headerHtml = $dom->saveHTML($header);
        $headerSearchButton = $xpath->query('.//button[@aria-label="Search"]', $header);
        expect($headerSearchButton->length)->toBe(0)
            ->and($headerHtml)->not->toContain('utilities.search-input')
            ->and($headerHtml)->not->toContain('Search tasks')
            ->and($headerHtml)->not->toContain('Search notebooks');
    }
});

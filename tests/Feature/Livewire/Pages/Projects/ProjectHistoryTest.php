<?php

use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('redirects guests and forbids non-owners from viewing project history', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->get(route('project.history', $project))
        ->assertRedirect(route('login'));

    $this->actingAs($otherUser)
        ->get(route('project.history', $project))
        ->assertForbidden();
});

it('displays history navigation item in the sidebar right after decisions and marks it active', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)
        ->get(route('project.history', $project))
        ->assertSuccessful()
        ->assertSee('History')
        ->assertSee('How this project moved forward.');

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);

    $sidebarItems = $xpath->query('//a[@data-flux-sidebar-item]');
    $labels = [];
    foreach ($sidebarItems as $item) {
        $labels[] = trim(preg_replace('/\s+/', ' ', $item->textContent));
    }

    $decisionsIndex = array_search('Decisions', $labels, true);
    $historyIndex = array_search('History', $labels, true);

    expect($decisionsIndex)->not->toBeFalse()
        ->and($historyIndex)->toBe($decisionsIndex + 1);

    $activeHistory = $xpath->query('//a[@data-flux-sidebar-item and @data-current and contains(., "History")]');
    expect($activeHistory->length)->toBe(1);
});

it('renders weekly history events, area tags, item links, and uses the app selected-chip style', function () {
    Carbon::setTestNow('2026-11-22 14:00:00');

    $owner = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $frontend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Frontend', 'color' => 'cyan']);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $frontend->id,
        'title' => 'Ship the new design tokens',
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-17 15:00:00'),
    ]);

    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 12,
        'title' => 'Use Livewire single-file components',
        'decided_at' => '2026-11-18',
        'updated_at' => Carbon::parse('2026-11-18 12:00:00'),
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'Architecture',
        'created_at' => Carbon::parse('2026-11-16 10:00:00'),
    ]);
    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $owner->id,
        'order' => 1,
    ]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'brand-guidelines.pdf',
        'created_at' => Carbon::parse('2026-11-13 11:00:00'),
    ]);

    $response = $this->actingAs($owner)
        ->get(route('project.history', $project))
        ->assertSuccessful()
        ->assertSee('All')
        ->assertSee('Tasks')
        ->assertSee('Decisions')
        ->assertSee('Notebooks')
        ->assertSee('Files')
        ->assertSee('This week')
        ->assertSee('Nov 16–22')
        ->assertSee('1 task done, 1 decision, 1 notebook')
        ->assertSee('Last week')
        ->assertSee('Nov 9–15')
        ->assertSee('1 file')
        ->assertSee('Completed')
        ->assertSee('Ship the new design tokens')
        ->assertSee('Frontend')
        ->assertSee('Accepted decision')
        ->assertSee('#12 Use Livewire single-file components')
        ->assertSee('Created notebook')
        ->assertSee('Architecture')
        ->assertSee('Uploaded file')
        ->assertSee('brand-guidelines.pdf')
        ->assertSee(route('show.task', ['project' => $project, 'task' => $task]))
        ->assertSee(route('project.decisions.show', ['project' => $project, 'decision' => $decision]))
        ->assertSee(route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $page]))
        ->assertSee(route('project.files.preview', ['project' => $project, 'projectFile' => $file]));

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $allChip = $xpath->query('//button[@data-history-chip="all"]')->item(0);

    expect($allChip)->not->toBeNull()
        ->and($allChip->getAttribute('class'))->toContain('bg-ink')
        ->and($allChip->getAttribute('class'))->not->toContain('bg-black')
        ->and($allChip->getAttribute('class'))->not->toContain('bg-inverse-surface');

    Carbon::setTestNow();
});

it('filters history by event type and loads earlier weeks on demand', function () {
    Carbon::setTestNow('2026-11-22 14:00:00');

    $owner = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Week 0 Task',
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-20 10:00:00'),
    ]);

    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Week 1 Decision',
        'decided_at' => '2026-11-11',
        'updated_at' => Carbon::parse('2026-11-11 10:00:00'),
    ]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'Week 2 Notebook',
        'created_at' => Carbon::parse('2026-11-05 10:00:00'),
    ]);

    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'week-3-file.pdf',
        'created_at' => Carbon::parse('2026-10-30 10:00:00'),
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Older Week 4 Task',
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-10-21 10:00:00'),
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::projects.history.index', ['project' => $project])
        ->assertSee('Week 0 Task')
        ->assertSee('Week 1 Decision')
        ->assertSee('Show earlier weeks')
        ->assertDontSee('Older Week 4 Task');

    $component->call('showEarlierWeeks')
        ->assertSee('Older Week 4 Task')
        ->assertDontSee('Show earlier weeks');

    $component->call('selectType', 'decision')
        ->assertSee('#1 Week 1 Decision')
        ->assertDontSee('Week 0 Task')
        ->assertDontSee('Week 2 Notebook')
        ->assertDontSee('week-3-file.pdf');

    Carbon::setTestNow();
});

it('shows the empty state when nothing has happened in the project yet', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('project.history', $project))
        ->assertSuccessful()
        ->assertSee('Nothing has happened yet')
        ->assertSee('Completed tasks, accepted decisions, new notebooks, and uploaded files will appear here.')
        ->assertDontSee('Show earlier weeks');
});

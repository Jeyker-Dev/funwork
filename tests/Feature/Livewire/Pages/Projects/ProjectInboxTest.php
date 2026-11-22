<?php

use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('capturing an idea via Enter saves it, clears the input, and omits the separate Add to Inbox button', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('Capture an idea and press Enter')
        ->assertDontSee('Add to Inbox')
        ->set('form.content', 'Write release checklist')
        ->call('addItem')
        ->assertHasNoErrors()
        ->assertSet('form.content', '')
        ->assertDispatched('inbox-item-captured');

    expect($user->inboxItems()->where('project_id', $project->id)->count())->toBe(1)
        ->and($component->html())->toContain('wire:submit="addItem"');
});

test('capturing an empty idea fails validation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->set('form.content', '')
        ->call('addItem')
        ->assertHasErrors(['form.content' => 'required']);
});

test('groups inbox items into Today, This week, and Older using the user timezone and shows short relative ages', function () {
    // 2026-11-22 20:00:00 in America/New_York (EST UTC-5) == 2026-11-23 01:00:00 UTC
    $this->travelTo(Carbon::parse('2026-11-23 01:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // 2026-11-22 23:00 UTC == 18:00 local (Today, 2h ago)
    $todayItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Today local item',
        'created_at' => Carbon::parse('2026-11-22 23:00:00', 'UTC'),
    ]);

    // 2026-11-22 04:00 UTC == 2026-11-21 23:00 local (Yesterday local -> This week, even though same UTC date as Nov 22)
    $yesterdayBoundaryItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Yesterday local boundary item',
        'created_at' => Carbon::parse('2026-11-22 04:00:00', 'UTC'),
    ]);

    // 14 days ago -> Older (2w)
    $olderItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Older archive candidate',
        'created_at' => Carbon::parse('2026-11-09 01:00:00', 'UTC'),
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSeeInOrder([
            'Today',
            $todayItem->content,
            '2h',
            'This week',
            $yesterdayBoundaryItem->content,
            'Older',
            $olderItem->content,
            '2w',
        ])
        ->html();

    expect($html)->toContain('Today')
        ->and($html)->toContain('This week')
        ->and($html)->toContain('Older');
});

test('hides empty date groups when items only belong to one group', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Only today item',
        'created_at' => now()->subHours(2),
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('Today')
        ->assertDontSee('This week')
        ->assertDontSee('Older');
});

test('header shows the N to process count and hides Process inbox when empty', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('0 to process')
        ->assertDontSee('Process inbox');

    InboxItem::factory()->count(4)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('4 to process')
        ->assertSee('Process inbox');
});

test('row renders Make task, Make note, and muted delete button without red danger styling', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $item = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Check hover actions',
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('Make task')
        ->assertSee('Make note')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $deleteButtons = $xpath->query('//section[@aria-label="Inbox items"]//button[@title="Delete"]');

    expect($deleteButtons->length)->toBe(1)
        ->and($deleteButtons->item(0)->getAttribute('class'))->toContain('text-ink-muted')
        ->and($deleteButtons->item(0)->getAttribute('class'))->not->toContain('red')
        ->and($deleteButtons->item(0)->getAttribute('class'))->not->toContain('danger');
});

test('process mode displays items oldest first with N of M counter and progress bar', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $oldest = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'First oldest idea',
        'created_at' => now()->subDays(3),
    ]);
    InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Second newer idea',
        'created_at' => now()->subHours(2),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->call('openProcessMode')
        ->assertSet('isProcessing', true)
        ->assertSee('1 of 2')
        ->assertSee($oldest->content)
        ->assertSee('Captured 3 days ago')
        ->assertSee('Skip');

    $dom = new DOMDocument;
    @$dom->loadHTML($component->html());
    $xpath = new DOMXPath($dom);
    $bars = $xpath->query('//*[@role="progressbar"]');

    expect($bars->length)->toBe(1)
        ->and($bars->item(0)->getAttribute('aria-valuenow'))->toBe('1')
        ->and($bars->item(0)->getAttribute('aria-valuemax'))->toBe('2');
});

test('converting an item to a task from process mode removes it from inbox and advances to the next item', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $first = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Convert me first',
        'created_at' => now()->subDays(2),
    ]);
    $second = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Next item after task',
        'created_at' => now()->subHours(1),
    ]);

    Livewire::actingAs($user)
        ->test('projects.inbox.convert-to-task')
        ->call('loadModalForProcess', $first->id)
        ->set('form.projectId', $project->id)
        ->set('form.title', 'Convert me first')
        ->call('convert')
        ->assertNoRedirect()
        ->assertDispatched('item-converted');

    expect(Task::where('title', 'Convert me first')->exists())->toBeTrue()
        ->and(InboxItem::where('id', $first->id)->whereNull('deleted_at')->exists())->toBeFalse();

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->call('openProcessMode')
        ->assertSee('1 of 1')
        ->assertSee($second->content);

    // Simulating the live event while process mode was open with [first, second]
    $third = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Third item in queue',
        'created_at' => now()->subMinutes(10),
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->call('openProcessMode')
        ->assertSee('1 of 2')
        ->assertSee($second->content)
        ->dispatch('item-converted', id: $second->id)
        ->assertSet('isProcessing', true)
        ->assertSee('2 of 2')
        ->assertSee($third->content);
});

test('converting an item to a notebook from process mode removes it from inbox without redirecting', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $item = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Turn into notebook',
    ]);

    Livewire::actingAs($user)
        ->test('projects.inbox.convert-to-notebook')
        ->call('loadModalForProcess', $item->id)
        ->set('form.projectId', $project->id)
        ->set('form.name', 'Meeting Notes')
        ->set('form.description', 'Turn into notebook')
        ->call('convert')
        ->assertNoRedirect()
        ->assertDispatched('item-converted');

    expect(Notebook::where('name', 'Meeting Notes')->exists())->toBeTrue()
        ->and(InboxItem::where('id', $item->id)->whereNull('deleted_at')->exists())->toBeFalse();
});

test('deleting an item from process mode removes it from inbox and advances to the next item', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00', 'UTC'));

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $first = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Delete me first',
        'created_at' => now()->subDays(2),
    ]);
    $second = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Surviving second item',
        'created_at' => now()->subHours(2),
    ]);

    $page = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->call('openProcessMode')
        ->assertSee('1 of 2')
        ->assertSee($first->content);

    $first->delete();

    $page->dispatch('item-deleted', id: $first->id)
        ->assertSet('isProcessing', true)
        ->assertSee('2 of 2')
        ->assertSee($second->content);
});

test('skipping an item in process mode keeps the item in the inbox and advances to the next item', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00', 'UTC'));

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $first = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Skip me for later',
        'created_at' => now()->subDays(2),
    ]);
    $second = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Show me next',
        'created_at' => now()->subHours(1),
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->call('openProcessMode')
        ->assertSee('1 of 2')
        ->assertSee($first->content)
        ->call('skipCurrentItem')
        ->assertSet('isProcessing', true)
        ->assertSee('2 of 2')
        ->assertSee($second->content)
        ->call('skipCurrentItem')
        ->assertSet('isProcessing', false)
        ->assertSee('2 to process')
        ->assertSee($first->content);

    expect(InboxItem::whereIn('id', [$first->id, $second->id])->count())->toBe(2);
});

test('empty state Inbox zero appears initially and when no items remain after processing', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->assertSee('Inbox zero')
        ->assertSee('0 to process');

    $onlyItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Last item in inbox',
    ]);

    $page = Livewire::actingAs($user)
        ->test('pages::projects.inbox', ['project' => $project])
        ->call('openProcessMode')
        ->assertSee('1 of 1');

    $onlyItem->delete();

    $page->dispatch('item-deleted', id: $onlyItem->id)
        ->assertSet('isProcessing', false)
        ->assertDispatched('focus-capture-bar')
        ->assertSee('Inbox zero')
        ->assertSee('0 to process');
});

test('sidebar marks Inbox as the active item on the inbox route', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('project.inbox', $project));
    $response->assertSuccessful();

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $activeInboxLinks = $xpath->query('//a[@data-flux-sidebar-item and @data-current and contains(., "Inbox")]');

    expect($activeInboxLinks->length)->toBe(1);
});

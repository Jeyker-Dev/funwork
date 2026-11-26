<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ChangelogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('adds nullable last_seen_changelog_at column to users table', function () {
    expect(Schema::hasColumn('users', 'last_seen_changelog_at'))->toBeTrue();
});

it('redirects guests from whats-new and renders for authenticated users outside any project', function () {
    $this->get(route('whats-new'))
        ->assertRedirect(route('login'));

    $user = User::factory()->create(['name' => 'Alex Rivera']);

    $response = $this->actingAs($user)
        ->get(route('whats-new'))
        ->assertSuccessful()
        ->assertSee("What's new in FunWork")
        ->assertSee('Updates and improvements to the app.')
        ->assertSee('Back to projects')
        ->assertDontSee('FUNWORK CHANGELOG')
        ->assertDontSee('CURATED CONTINUOUSLY')
        ->assertDontSee('Search Projects');

    $html = $response->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Account settings top bar without project sidebar
    expect($xpath->query('//header[@data-flux-header]')->length)->toBe(1)
        ->and($xpath->query('//aside[@data-flux-sidebar]')->length)->toBe(0)
        ->and($html)->toContain('max-w-[760px]')
        ->and($html)->not->toContain('arrow_back')
        ->and($html)->not->toContain('arrow_forward')
        ->and($html)->not->toContain('→')
        ->and($html)->not->toContain('←');
});

it('renders seeded releases with colored pills, quiet links, and marks groups newer than last visit before updating last_seen_changelog_at', function () {
    Carbon::setTestNow('2026-11-26 15:00:00');

    $user = User::factory()->create([
        'last_seen_changelog_at' => Carbon::parse('2026-11-25 18:00:00'),
    ]);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $firstVisit = $this->actingAs($user)
        ->get(route('whats-new'))
        ->assertSuccessful()
        ->assertSee('November 26, 2026')
        ->assertSee('November 25, 2026')
        ->assertSee('November 24, 2026')
        ->assertSee('November 23, 2026')
        ->assertSee('November 22, 2026')
        ->assertSee('November 21, 2026')
        ->assertSee('New since your last visit')
        ->assertSee('New')
        ->assertSee('Improved')
        ->assertSee('Fixed')
        ->assertSee('Open Tasks')
        ->assertSee('Open History')
        ->assertSee('Open Notebooks')
        ->assertSee('Open Files')
        ->assertSee('Open Account settings')
        ->assertSee('Open Project settings')
        ->assertSee('Open Notification settings')
        ->assertSee('Open Integrations')
        ->assertSee(route('project.tasks', ['project' => $project]))
        ->assertSee(route('project.settings', ['project' => $project]))
        ->assertSee(route('notifications.edit'))
        ->assertSee(route('integrations.edit'))
        ->assertSee(route('profile.edit'));

    $firstHtml = $firstVisit->getContent();
    expect($firstHtml)
        ->toContain('bg-emerald-50')
        ->toContain('bg-blue-50')
        ->toContain('bg-amber-50');

    // Only the Nov 26 group should be marked as new since last visit (Nov 25 18:00)
    expect(substr_count($firstHtml, 'New since your last visit'))->toBe(1);

    expect($user->fresh()->last_seen_changelog_at?->toDateTimeString())->toBe('2026-11-26 15:00:00');

    // Second visit immediately after should not mark any group as new
    $this->actingAs($user->fresh())
        ->get(route('whats-new'))
        ->assertSuccessful()
        ->assertDontSee('New since your last visit');

    Carbon::setTestNow();
});

it('omits project links on whats-new when the user has no projects while keeping non-project links', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('whats-new'))
        ->assertSuccessful()
        ->assertSee('Open Account settings')
        ->assertSee(route('profile.edit'))
        ->assertSee('Open Notification settings')
        ->assertSee(route('notifications.edit'))
        ->assertSee('Open Integrations')
        ->assertSee(route('integrations.edit'))
        ->assertSee('Open Projects')
        ->assertSee(route('projects'))
        ->assertDontSee('Open History')
        ->assertDontSee('Open Notebooks')
        ->assertDontSee('Open Files')
        ->assertDontSee('Open Project settings');
});

it('replaces Change Log button in sidebar with What\'s new link and shows unread dot based on last_seen_changelog_at', function () {
    Carbon::setTestNow('2026-11-26 15:00:00');

    $neverSeenUser = User::factory()->create(['last_seen_changelog_at' => null]);
    $project = Project::factory()->create(['owner_id' => $neverSeenUser->id]);

    $unreadHtml = $this->actingAs($neverSeenUser)
        ->get(route('projects.show', $project))
        ->assertSuccessful()
        ->assertSee("What's new")
        ->assertSee(route('whats-new'))
        ->assertDontSee('Change Log')
        ->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML($unreadHtml);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@aria-label="New updates"]')->length)->toBe(1);

    $seenUser = User::factory()->create(['last_seen_changelog_at' => Carbon::parse('2026-11-26 12:00:00')]);
    $seenProject = Project::factory()->create(['owner_id' => $seenUser->id]);

    $readHtml = $this->actingAs($seenUser)
        ->get(route('projects.show', $seenProject))
        ->assertSuccessful()
        ->assertSee("What's new")
        ->getContent();

    $readDom = new DOMDocument;
    @$readDom->loadHTML($readHtml);
    $readXpath = new DOMXPath($readDom);

    expect($readXpath->query('//*[@aria-label="New updates"]')->length)->toBe(0);

    // When no releases exist, null last_seen_changelog_at does not show the unread dot
    $emptyDir = storage_path('framework/testing/empty-changelog-'.uniqid());
    File::ensureDirectoryExists($emptyDir);

    try {
        app()->instance(ChangelogService::class, new ChangelogService($emptyDir));

        $noReleasesHtml = $this->actingAs($neverSeenUser)
            ->get(route('projects.show', $project))
            ->assertSuccessful()
            ->getContent();

        $noReleasesDom = new DOMDocument;
        @$noReleasesDom->loadHTML($noReleasesHtml);
        $noReleasesXpath = new DOMXPath($noReleasesDom);

        expect($noReleasesXpath->query('//*[@aria-label="New updates"]')->length)->toBe(0);
    } finally {
        File::deleteDirectory($emptyDir);
    }

    Carbon::setTestNow();
});

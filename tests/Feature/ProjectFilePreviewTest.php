<?php

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('s3');
});

it('streams local file inline', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Storage::disk('local')->put('test.png', 'fake image content');

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'disk' => 'local',
        'file_path' => 'test.png',
        'name' => 'test.png',
        'mime_type' => 'image/png',
    ]);

    $this->actingAs($user)
        ->get(route('project.files.preview', [$project, $file]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="test.png"');
});

it('redirects to temporary url for s3 file with inline disposition', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Storage::disk('s3')->put('test.pdf', 'fake pdf content');

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'disk' => 's3',
        'file_path' => 'test.pdf',
        'name' => 'test.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->actingAs($user)
        ->get(route('project.files.preview', [$project, $file]));

    $response->assertRedirect();
    $redirectUrl = $response->headers->get('Location');

    expect($redirectUrl)->toContain('test.pdf');
});

it('aborts 404 if file does not belong to project', function () {
    $user = User::factory()->create();
    $project1 = Project::factory()->create(['owner_id' => $user->id]);
    $project2 = Project::factory()->create(['owner_id' => $user->id]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project1->id,
    ]);

    $this->actingAs($user)
        ->get(route('project.files.preview', [$project2, $file]))
        ->assertNotFound();
});

it('requires authentication', function () {
    $project = Project::factory()->create();
    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
    ]);

    $this->get(route('project.files.preview', [$project, $file]))
        ->assertRedirect(route('login'));
});

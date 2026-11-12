<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

test('areas table and columns exist in database schema', function () {
    expect(Schema::hasTable('areas'))->toBeTrue()
        ->and(Schema::hasColumns('areas', ['id', 'project_id', 'name', 'slug', 'color', 'position', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'area_id'))->toBeTrue()
        ->and(Schema::hasColumn('notebooks', 'area_id'))->toBeTrue();
});

test('data migration populates default areas for existing projects that have none', function () {
    $user = User::factory()->create();
    // Simulate a project created before areas existed (no areas)
    $project = Project::factory()->create(['owner_id' => $user->id]);
    expect($project->areas()->count())->toBe(0);

    // Run the action that the data migration runs
    $migration = new class extends Migration
    {
        public function up(): void
        {
            $action = app(CreateDefaultAreasAction::class);
            foreach (Project::doesntHave('areas')->cursor() as $project) {
                $action($project);
            }
        }
    };

    $migration->up();

    $areas = $project->areas()->orderBy('position')->get();
    expect($areas)->toHaveCount(4)
        ->and($areas->pluck('slug')->all())->toBe(['frontend', 'backend', 'database', 'design']);
});

test('data migration does not duplicate areas for projects that already have areas', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $project->areas()->create([
        'name' => 'Custom',
        'slug' => 'custom',
        'color' => 'zinc',
        'position' => 1,
    ]);
    expect($project->areas()->count())->toBe(1);

    $migration = new class extends Migration
    {
        public function up(): void
        {
            $action = app(CreateDefaultAreasAction::class);
            foreach (Project::doesntHave('areas')->cursor() as $project) {
                $action($project);
            }
        }
    };

    $migration->up();

    expect($project->areas()->count())->toBe(1);
});

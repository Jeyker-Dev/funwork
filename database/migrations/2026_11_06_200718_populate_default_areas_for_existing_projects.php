<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $action = app(CreateDefaultAreasAction::class);

        foreach (Project::doesntHave('areas')->cursor() as $project) {
            $action($project);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration rollback: leave areas as-is or delete default areas if project was created prior
    }
};

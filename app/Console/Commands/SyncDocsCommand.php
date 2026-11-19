<?php

namespace App\Console\Commands;

use App\Actions\Notebooks\SyncDocsNotebookAction;
use App\Models\Area;
use App\Models\Project;
use Illuminate\Console\Command;

class SyncDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "funwork:sync-docs {project : ID del proyecto} {--path= : carpeta de docs, por defecto base_path('docs')} {--area= : area slug; mirrors into notebook \"Docs · <Area name>\" linked to that area}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Mirror repository docs into the project's Docs notebook";

    public function __construct(
        private SyncDocsNotebookAction $syncDocsNotebook,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projectId = $this->argument('project');
        $project = Project::find($projectId);

        if (! $project instanceof Project) {
            $this->error("Project [{$projectId}] not found.");

            return self::FAILURE;
        }

        $areaSlug = $this->option('area');
        $area = null;

        if (is_string($areaSlug) && $areaSlug !== '') {
            $area = $project->areaByKey($areaSlug);

            if (! $area instanceof Area) {
                $this->error("Area [{$areaSlug}] not found in project [{$project->id}].");

                return self::FAILURE;
            }
        }

        $docsPath = (string) ($this->option('path') ?: base_path('docs'));

        if (! is_dir($docsPath)) {
            $this->error("Docs directory [{$docsPath}] not found.");

            return self::FAILURE;
        }

        $counts = ($this->syncDocsNotebook)($project, $docsPath, $area);

        $this->info("Docs sincronizados: {$counts['created']} creadas, {$counts['updated']} actualizadas, {$counts['deleted']} borradas.");

        return self::SUCCESS;
    }
}

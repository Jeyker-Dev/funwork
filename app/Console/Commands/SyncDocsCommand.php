<?php

namespace App\Console\Commands;

use App\Jobs\SyncNotebookToGoogleDocJob;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

class SyncDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "funwork:sync-docs {project : ID del proyecto} {--path= : carpeta de docs, por defecto base_path('docs')}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Mirror repository docs into the project's Docs notebook";

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projectId = $this->argument('project');
        $docsPath = $this->option('path') ?: base_path('docs');

        return DB::transaction(function () use ($projectId, $docsPath) {
            $project = Project::find($projectId);

            if (! $project) {
                $this->error("Project [{$projectId}] not found.");

                return self::FAILURE;
            }

            if (! is_dir($docsPath)) {
                $this->error("Docs directory [{$docsPath}] not found.");

                return self::FAILURE;
            }

            $notebook = Notebook::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => 'Docs',
                ],
                [
                    'created_by' => $project->owner_id,
                ]
            );

            $files = [];
            $finder = (new Finder)
                ->files()
                ->in($docsPath)
                ->name('*.md')
                ->exclude('archive');

            foreach ($finder as $file) {
                $relativePath = str_replace('\\', '/', $file->getRelativePathname());

                if (str_starts_with($relativePath, 'archive/') || $relativePath === 'archive') {
                    continue;
                }

                $sourcePath = 'docs/'.ltrim($relativePath, '/');
                $files[$sourcePath] = $file->getRealPath();
            }

            ksort($files, SORT_NATURAL | SORT_FLAG_CASE);

            $created = 0;
            $updated = 0;
            $order = 1;
            $syncedSourcePaths = [];

            NotebookPage::withoutEvents(function () use ($files, $notebook, $project, &$created, &$updated, &$order, &$syncedSourcePaths) {
                foreach ($files as $sourcePath => $filePath) {
                    $syncedSourcePaths[] = $sourcePath;
                    $body = file_get_contents($filePath);
                    $content = [
                        'title' => $sourcePath,
                        'body' => $body,
                    ];

                    $page = $notebook->pages()->where('source_path', $sourcePath)->first();

                    if (! $page) {
                        $notebook->pages()->create([
                            'source_path' => $sourcePath,
                            'content' => $content,
                            'created_by' => $project->owner_id,
                            'order' => $order,
                        ]);
                        $created++;
                    } else {
                        $isContentDifferent = ! is_array($page->content)
                            || ($page->content['title'] ?? null) !== $content['title']
                            || ($page->content['body'] ?? null) !== $content['body'];

                        $isOrderDifferent = (int) $page->order !== $order;
                        $isOwnerDifferent = (int) $page->created_by !== (int) $project->owner_id;

                        if ($isContentDifferent || $isOrderDifferent || $isOwnerDifferent) {
                            $page->update([
                                'content' => $content,
                                'created_by' => $project->owner_id,
                                'order' => $order,
                            ]);
                            $updated++;
                        }
                    }

                    $order++;
                }
            });

            $deleteQuery = $notebook->pages()->whereNotNull('source_path');

            if (! empty($syncedSourcePaths)) {
                $deleteQuery->whereNotIn('source_path', $syncedSourcePaths);
            }

            $deleted = $deleteQuery->delete();

            if ($created + $updated + $deleted > 0) {
                $notebook->forceFill([
                    'content_changed_at' => now(),
                ])->save();

                if ($notebook->google_doc_id) {
                    SyncNotebookToGoogleDocJob::dispatch($notebook)->delay(now()->addMinutes(2));
                }
            }

            $this->info("Docs sincronizados: {$created} creadas, {$updated} actualizadas, {$deleted} borradas.");

            return self::SUCCESS;
        });
    }
}

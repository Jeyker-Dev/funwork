<?php

namespace App\Actions\Notebooks;

use App\Jobs\SyncNotebookToGoogleDocJob;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors markdown documentation files into a project's Docs notebook.
 */
class SyncDocsNotebookAction
{
    private const string DEFAULT_NOTEBOOK_NAME = 'Docs';

    private const string AREA_NOTEBOOK_PREFIX = 'Docs · ';

    private const int GOOGLE_DOC_SYNC_DELAY_MINUTES = 2;

    /**
     * @param  array<string, string>  $files
     * @return array{created: int, updated: int, deleted: int}
     */
    public function __invoke(Project $project, array $files, ?Area $area = null): array
    {
        return DB::transaction(function () use ($project, $files, $area): array {
            $notebook = $this->resolveNotebook($project, $area);
            ['created' => $created, 'updated' => $updated] = $this->syncPages($notebook, $project, $files);
            $deleted = $this->pruneStalePages($notebook, array_keys($files));

            if ($created + $updated + $deleted > 0) {
                $this->recordNotebookChanged($notebook);
            }

            return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
        });
    }

    private function resolveNotebook(Project $project, ?Area $area): Notebook
    {
        if (! $area instanceof Area) {
            return Notebook::firstOrCreate(
                ['project_id' => $project->id, 'name' => self::DEFAULT_NOTEBOOK_NAME],
                ['created_by' => $project->owner_id],
            );
        }

        $notebookName = self::AREA_NOTEBOOK_PREFIX.$area->name;
        $notebook = Notebook::query()
            ->where('project_id', $project->id)
            ->where('area_id', $area->id)
            ->where('name', 'like', self::AREA_NOTEBOOK_PREFIX.'%')
            ->orderBy('id')
            ->first();

        if (! $notebook instanceof Notebook) {
            return Notebook::create([
                'project_id' => $project->id,
                'area_id' => $area->id,
                'name' => $notebookName,
                'created_by' => $project->owner_id,
            ]);
        }

        if ($notebook->name !== $notebookName) {
            $notebook->update(['name' => $notebookName]);
        }

        return $notebook;
    }

    /**
     * @param  array<string, string>  $files
     * @return array{created: int, updated: int}
     */
    private function syncPages(Notebook $notebook, Project $project, array $files): array
    {
        $existingPages = $notebook->pages()->whereNotNull('source_path')->get()->keyBy('source_path');
        $created = 0;
        $updated = 0;
        $order = 1;

        NotebookPage::withoutEvents(function () use ($files, $existingPages, $notebook, $project, &$created, &$updated, &$order): void {
            foreach ($files as $sourcePath => $body) {
                $content = ['title' => $sourcePath, 'body' => $body];
                $page = $existingPages->get($sourcePath);

                if (! $page instanceof NotebookPage) {
                    $notebook->pages()->create([
                        'source_path' => $sourcePath,
                        'content' => $content,
                        'created_by' => $project->owner_id,
                        'order' => $order,
                    ]);
                    $created++;
                } elseif ($this->isPageOutdated($page, $content, $order, (int) $project->owner_id)) {
                    $page->update([
                        'content' => $content,
                        'created_by' => $project->owner_id,
                        'order' => $order,
                    ]);
                    $updated++;
                }

                $order++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param  array{title: string, body: string}  $content
     */
    private function isPageOutdated(NotebookPage $page, array $content, int $order, int $ownerId): bool
    {
        return ! is_array($page->content)
            || ($page->content['title'] ?? null) !== $content['title']
            || ($page->content['body'] ?? null) !== $content['body']
            || (int) $page->order !== $order
            || (int) $page->created_by !== $ownerId;
    }

    /**
     * @param  list<string>  $syncedSourcePaths
     */
    private function pruneStalePages(Notebook $notebook, array $syncedSourcePaths): int
    {
        $deleteQuery = $notebook->pages()->whereNotNull('source_path');

        if ($syncedSourcePaths !== []) {
            $deleteQuery->whereNotIn('source_path', $syncedSourcePaths);
        }

        return $deleteQuery->delete();
    }

    private function recordNotebookChanged(Notebook $notebook): void
    {
        $notebook->forceFill(['content_changed_at' => now()])->save();

        if ($notebook->google_doc_id) {
            SyncNotebookToGoogleDocJob::dispatch($notebook)
                ->delay(now()->addMinutes(self::GOOGLE_DOC_SYNC_DELAY_MINUTES));
        }
    }
}

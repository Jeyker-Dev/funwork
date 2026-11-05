<?php

namespace App\Actions\Projects;

use App\Enums\TaskStatus;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Builds a single Markdown document with a project's tasks and notebooks,
 * meant to be uploaded to NotebookLM as one source.
 *
 * It does not authorize: callers check access to the project first.
 */
class BuildProjectPackAction
{
    public function execute(Project $project): string
    {
        $sections = [
            "# {$project->name} — Project pack",
            'Generado por FunWork el '.now()->utc()->format('Y-m-d H:i').' UTC.',
        ];

        if (filled($project->description)) {
            $sections[] = $project->description;
        }

        $sections[] = $this->tasksSection($project);
        $sections[] = $this->notebooksSection($project);

        return implode("\n\n", $sections)."\n";
    }

    private function tasksSection(Project $project): string
    {
        $tasks = $project->tasks()->with('users')->orderBy('position')->orderBy('id')->get();

        if ($tasks->isEmpty()) {
            return "## Tareas\n\n_Sin tareas._";
        }

        $blocks = ['## Tareas'];

        foreach (TaskStatus::cases() as $status) {
            $group = $tasks->filter(fn (Task $task) => $task->status === $status);

            $blocks[] = "### {$status->value} ({$group->count()})";

            if ($group->isNotEmpty()) {
                $blocks[] = $group->map(fn (Task $task) => $this->taskLine($task))->implode("\n\n");
            }
        }

        return implode("\n\n", $blocks);
    }

    private function taskLine(Task $task): string
    {
        $details = array_filter([
            $task->priority ? "prioridad: {$task->priority->value}" : null,
            $task->due_date ? 'vence: '.$task->due_date->format('Y-m-d') : null,
            $task->users->isNotEmpty()
                ? 'asignada a: '.$task->users->pluck('display_name')->implode(', ')
                : null,
        ]);

        $line = "- **{$task->title}**".($details ? ' — '.implode(' · ', $details) : '');

        if (filled($task->description)) {
            $line .= "\n\n".$this->indent($task->description);
        }

        return $line;
    }

    private function notebooksSection(Project $project): string
    {
        /** @var Collection<int, Notebook> $notebooks */
        $notebooks = $project->notebooks()
            ->where('is_archived', false)
            ->with(['pages' => fn ($query) => $query->orderBy('order')->orderBy('id')])
            ->orderBy('name')
            ->get();

        if ($notebooks->isEmpty()) {
            return "## Notebooks\n\n_Sin notebooks._";
        }

        $blocks = ['## Notebooks'];

        foreach ($notebooks as $notebook) {
            $blocks[] = "### {$notebook->name}";

            if (filled($notebook->description)) {
                $blocks[] = $notebook->description;
            }

            foreach ($notebook->pages->values() as $index => $page) {
                $content = is_array($page->content) ? $page->content : [];
                $title = filled($content['title'] ?? null) ? $content['title'] : 'Página '.($index + 1);

                $blocks[] = "#### {$title}";

                if (filled($content['body'] ?? null)) {
                    $blocks[] = trim($content['body']);
                }
            }
        }

        return implode("\n\n", $blocks);
    }

    private function indent(string $text): string
    {
        return collect(preg_split('/\R/', trim($text)))
            ->map(fn (string $line) => $line === '' ? '' : "  {$line}")
            ->implode("\n");
    }
}

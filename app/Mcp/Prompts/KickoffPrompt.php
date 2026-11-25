<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('kickoff')]
#[Title('Project Kickoff')]
#[Description('Guide a structured project kickoff in FunWork: clarify the idea, propose a plan (Vision page, ADRs, and initial tasks per area), ask for confirmation, and only then write to FunWork.')]
class KickoffPrompt extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'idea',
                description: 'Optional initial project idea or brief description to discuss.',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'idea' => ['nullable', 'string'],
        ]);

        $idea = filled($validated['idea'] ?? null) ? trim($validated['idea']) : null;

        $ideaSection = $idea !== null
            ? "Initial project idea from the user:\n> {$idea}\n\n"
            : "The user has not provided an initial idea yet. Start by asking what they want to build.\n\n";

        $instructions = <<<MARKDOWN
        You are helping the user kick off a new project in FunWork. Follow this workflow strictly in order:

        {$ideaSection}## Phase 1: Discuss the idea first (do NOT write anything yet)
        - Do not call any write tools yet (`create_project`, `create_notebook`, `add_notebook_page`, `log_decision`, `add_tasks`, `add_task`, or `add_inbox_item`).
        - Discuss and clarify the idea with the user across five dimensions:
          1. **Problem**: What problem does this project solve?
          2. **Goal**: What does success look like for the first version?
          3. **Users**: Who will use it?
          4. **Scope**: What is in scope for v1 and what is explicitly out of scope?
          5. **Stack**: Which technologies, frameworks, database, and architecture fit best (and why)?
        - Ask focused questions until these points are clear.

        ## Phase 2: Propose a concrete FunWork plan and ask for confirmation
        Once the idea is clear, present a structured plan for the user to review before creating anything:
        1. **Project**: Name, short description, project type, and initial status.
        2. **Vision notebook page**: Outline of the "Vision" notebook page (problem, goal, target users, v1 scope, and non-goals) to add to a project notebook.
        3. **Architecture Decision Records (ADRs)**: One ADR per stack or architecture choice (title, context, decision, consequences, status) to record via `log_decision`.
        4. **First tasks per area**: Starter tasks grouped by project area (`frontend`, `backend`, `database`, `design`) with title, acceptance criteria description, priority, and area.
        - Explicitly ask the user to confirm or adjust the plan before writing anything to FunWork.

        ## Phase 3: Write to FunWork (only after user confirmation)
        Only after the user confirms the plan, execute the writes in order:
        1. Call `create_project` to create the project (it returns the project ID and its areas).
        2. Call `create_notebook` to create a project notebook (e.g. `Docs` or `Vision`), then call `add_notebook_page` to add the "Vision" Markdown page (`title` and `body`).
        3. Call `log_decision` once for each approved stack or architecture choice.
        4. Call `add_tasks` to create the approved starter tasks across the project areas.

        ## Phase 4: Verify and summarize
        - Finish by calling `get_project_brief` for the new project ID.
        - Summarize what was created in FunWork (project, Vision notebook page, ADRs, and tasks) based on the returned brief.
        MARKDOWN;

        return Response::text($instructions);
    }
}

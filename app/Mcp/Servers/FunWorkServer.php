<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\KickoffPrompt;
use App\Mcp\Tools\AddInboxItemTool;
use App\Mcp\Tools\AddNotebookPageTool;
use App\Mcp\Tools\AddTasksTool;
use App\Mcp\Tools\AddTaskTool;
use App\Mcp\Tools\CreateNotebookTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\GetProjectBriefTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListDecisionsTool;
use App\Mcp\Tools\ListInboxItemsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\LogDecisionTool;
use App\Mcp\Tools\ReadNotebookTool;
use App\Mcp\Tools\UpdateTaskDescriptionTool;
use App\Mcp\Tools\UpdateTaskStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('FunWork MCP Server')]
#[Version('1.0.0')]
#[Instructions('Interact with the authenticated user\'s FunWork projects, tasks, inbox items, notebooks, and decisions.')]
class FunWorkServer extends Server
{
    public int $defaultPaginationLength = 50;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        GetProjectBriefTool::class,
        CreateProjectTool::class,
        CreateNotebookTool::class,
        AddNotebookPageTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        UpdateTaskStatusTool::class,
        UpdateTaskDescriptionTool::class,
        AddTaskTool::class,
        AddTasksTool::class,
        AddInboxItemTool::class,
        ListInboxItemsTool::class,
        ReadNotebookTool::class,
        ListDecisionsTool::class,
        LogDecisionTool::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        KickoffPrompt::class,
    ];
}

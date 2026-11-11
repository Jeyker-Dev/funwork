<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddInboxItemTool;
use App\Mcp\Tools\AddTaskTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\ReadNotebookTool;
use App\Mcp\Tools\UpdateTaskDescriptionTool;
use App\Mcp\Tools\UpdateTaskStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('FunWork MCP Server')]
#[Version('1.0.0')]
#[Instructions('Interact with the authenticated user\'s FunWork projects, tasks, inbox items, and notebooks.')]
class FunWorkServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        UpdateTaskStatusTool::class,
        UpdateTaskDescriptionTool::class,
        AddTaskTool::class,
        AddInboxItemTool::class,
        ReadNotebookTool::class,
    ];
}

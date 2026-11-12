<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class FunWorkTool extends Tool
{
    /**
     * The tool authorization error message.
     */
    protected string $authorizationMessage = 'This action is unauthorized.';

    /**
     * Get the tool authorization error message.
     */
    protected function authorizationMessage(): string
    {
        return $this->authorizationMessage;
    }

    /**
     * Handle the incoming MCP tool request using the template method pattern.
     */
    final public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        try {
            return $this->execute($request, $user);
        } catch (AuthorizationException) {
            return Response::error($this->authorizationMessage());
        } catch (ModelNotFoundException $e) {
            $model = $e->getModel() !== '' ? class_basename($e->getModel()) : 'Resource';

            return Response::error(sprintf('%s not found.', $model));
        }
    }

    /**
     * Execute the tool logic for an authenticated user.
     */
    abstract protected function execute(Request $request, User $user): Response;
}

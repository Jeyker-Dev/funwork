<?php

use App\Mcp\Tools\FunWorkTool;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

test('funwork tool returns unauthenticated when no user is resolved', function () {
    Auth::logout();

    $tool = new class extends FunWorkTool
    {
        protected function execute(Request $request, User $user): Response
        {
            return Response::json(['ok' => true]);
        }
    };

    $response = $tool->handle(new Request);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toBe('Unauthenticated.');
});

test('funwork tool translates AuthorizationException into a tool error response with the custom message', function () {
    $user = User::factory()->create();
    Auth::setUser($user);

    $tool = new class extends FunWorkTool
    {
        protected string $authorizationMessage = 'You are not authorized for this custom action.';

        protected function execute(Request $request, User $user): Response
        {
            throw new AuthorizationException;
        }
    };

    $response = $tool->handle(new Request);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toBe('You are not authorized for this custom action.');
});

test('funwork tool translates ModelNotFoundException into a tool error response with model name', function () {
    $user = User::factory()->create();
    Auth::setUser($user);

    $tool = new class extends FunWorkTool
    {
        protected function execute(Request $request, User $user): Response
        {
            throw (new ModelNotFoundException)->setModel(Task::class);
        }
    };

    $response = $tool->handle(new Request);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toBe('Task not found.');
});

test('funwork tool does not convert unexpected exceptions into a Response error', function () {
    $user = User::factory()->create();
    Auth::setUser($user);

    $tool = new class extends FunWorkTool
    {
        protected function execute(Request $request, User $user): Response
        {
            throw new RuntimeException('Unexpected failure that must be logged, never swallowed.');
        }
    };

    expect(fn () => $tool->handle(new Request))
        ->toThrow(RuntimeException::class, 'Unexpected failure that must be logged, never swallowed.');
});

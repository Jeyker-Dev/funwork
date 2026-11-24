<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberLastVisitedProject
{
    public const string SESSION_KEY = 'last_project_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');
        $user = $request->user();

        if ($project instanceof Project && $user !== null && $user->can('view', $project)) {
            $request->session()->put(self::SESSION_KEY, $project->id);
        }

        return $next($request);
    }
}

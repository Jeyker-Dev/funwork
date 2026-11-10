<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpWithApiToken
{
    /**
     * Handle an incoming MCP request authenticated with a Bearer API token.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! is_string($plainTextToken) || trim($plainTextToken) === '') {
            return $this->unauthorizedResponse();
        }

        $hashedToken = ApiToken::hashToken(trim($plainTextToken));

        $apiToken = ApiToken::with('user')
            ->where('token', $hashedToken)
            ->first();

        if ($apiToken === null || $apiToken->isExpired()) {
            return $this->unauthorizedResponse();
        }

        $apiToken->markAsUsed();

        Auth::setUser($apiToken->user);
        $request->setUserResolver(static fn () => $apiToken->user);

        return $next($request);
    }

    private function unauthorizedResponse(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}

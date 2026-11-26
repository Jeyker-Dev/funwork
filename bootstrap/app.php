<?php

use App\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app is only reachable through the Caddy proxy on the internal network,
        // so its X-Forwarded-* headers are trusted to detect HTTPS and the client IP.
        $middleware->trustProxies(at: '*');
        $middleware->preventRequestForgery(except: [
            'webhooks/github/repository-connections/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(BusinessRuleException::class);
    })->create();

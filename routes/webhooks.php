<?php

use App\Http\Controllers\GitHubRepositoryWebhookController;
use Illuminate\Support\Facades\Route;

// Public stateless route: GitHub repository webhooks verify HMAC-SHA256 signatures per connection.
Route::post('/webhooks/github/repository-connections/{connection}', GitHubRepositoryWebhookController::class)
    ->middleware('throttle:github-webhooks')
    ->name('webhooks.github.repository-connections');

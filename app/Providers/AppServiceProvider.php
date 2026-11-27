<?php

namespace App\Providers;

use App\Actions\AiConversations\SendAiMessageAction;
use App\Contracts\RepositoryDocsSource;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Policies\RepositoryConnectionPolicy;
use App\Services\AiProviders\AiProviderResolver;
use App\Services\GitHub\GitHubTokenDocsSource;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RepositoryDocsSource::class, GitHubTokenDocsSource::class);
        $this->app->scoped(AiProviderResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        Gate::policy(RepositoryConnection::class, RepositoryConnectionPolicy::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('github-webhooks', function (Request $request): Limit {
            $connection = $request->route('connection');
            $connectionKey = $connection instanceof RepositoryConnection
                ? (string) $connection->getKey()
                : (string) $connection;

            return Limit::perMinute(60)->by($connectionKey !== '' ? $connectionKey : (string) $request->ip());
        });

        RateLimiter::for(SendAiMessageAction::RATE_LIMITER_NAME, function (User $user): Limit {
            return Limit::perHour(SendAiMessageAction::MAX_MESSAGES_PER_HOUR)
                ->by('ai-messages:'.$user->getKey());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn () => Password::min(6));
    }
}

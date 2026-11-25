<?php

namespace Database\Factories;

use App\Enums\RepositoryConnectionStatus;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RepositoryConnection>
 */
class RepositoryConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'area_id' => null,
            'repository' => fake()->slug(1).'/'.fake()->slug(2),
            'branch' => 'main',
            'docs_path' => 'docs',
            'token' => 'ghp_'.Str::random(36),
            'webhook_secret' => RepositoryConnection::generateWebhookSecret(),
            'last_synced_sha' => null,
            'last_synced_at' => null,
            'status' => RepositoryConnectionStatus::Active,
            'last_error' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RepositoryConnectionStatus::Active,
            'last_error' => null,
        ]);
    }

    public function broken(string $error = 'GitHub returned 401 Unauthorized.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RepositoryConnectionStatus::Broken,
            'last_error' => $error,
        ]);
    }
}

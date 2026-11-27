<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiConnection>
 */
class AiConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::OpenAi,
            'label' => null,
            'base_url' => null,
            'model' => 'gpt-5',
            'api_key' => 'sk-test-'.Str::random(24),
            'allow_paid_models' => false,
            'is_default' => false,
        ];
    }

    public function openRouter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::OpenRouter,
            'base_url' => null,
            'model' => 'meta-llama/llama-3.3-70b-instruct:free',
            'allow_paid_models' => false,
        ]);
    }

    public function custom(string $baseUrl = 'https://llm.example.com/v1'): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::Custom,
            'base_url' => $baseUrl,
            'model' => 'custom-model',
            'allow_paid_models' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}

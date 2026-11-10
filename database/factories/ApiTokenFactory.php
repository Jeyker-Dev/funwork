<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainTextToken = ApiToken::TOKEN_PREFIX.Str::random(40);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token' => ApiToken::hashToken($plainTextToken),
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function withPlainTextToken(string $plainTextToken): static
    {
        return $this->state(fn (array $attributes): array => [
            'token' => ApiToken::hashToken($plainTextToken),
        ]);
    }
}

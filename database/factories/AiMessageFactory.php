<?php

namespace Database\Factories;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => AiMessageRole::User,
            'content' => fake()->sentence(),
            'model' => null,
            'status' => AiMessageStatus::Completed,
            'error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AiMessageRole::Assistant,
            'content' => '',
            'model' => null,
            'status' => AiMessageStatus::Pending,
            'error' => null,
        ]);
    }

    public function failed(string $error = 'Something went wrong. Try again.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AiMessageRole::Assistant,
            'content' => '',
            'status' => AiMessageStatus::Failed,
            'error' => $error,
        ]);
    }
}

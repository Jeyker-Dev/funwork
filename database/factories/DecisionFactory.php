<?php

namespace Database\Factories;

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Decision>
 */
class DecisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => fake()->unique()->numberBetween(1, 100000),
            'title' => fake()->sentence(5),
            'status' => DecisionStatus::Proposed,
            'context' => fake()->paragraph(),
            'decision' => fake()->paragraph(),
            'consequences' => fake()->paragraph(),
            'decided_at' => null,
            'superseded_by_id' => null,
            'created_by' => User::factory(),
        ];
    }

    public function proposed(): static
    {
        return $this->state(fn (): array => [
            'status' => DecisionStatus::Proposed,
            'decided_at' => null,
            'superseded_by_id' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => DecisionStatus::Accepted,
            'decided_at' => fake()->date(),
            'superseded_by_id' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => DecisionStatus::Rejected,
            'decided_at' => fake()->date(),
            'superseded_by_id' => null,
        ]);
    }

    public function superseded(?Decision $supersededBy = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DecisionStatus::Superseded,
            'decided_at' => fake()->date(),
            'superseded_by_id' => $supersededBy?->id ?? Decision::factory()->state([
                'project_id' => $attributes['project_id'],
                'created_by' => $attributes['created_by'],
            ]),
        ]);
    }
}

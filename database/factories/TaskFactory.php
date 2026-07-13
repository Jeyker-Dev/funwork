<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['Todo', 'Doing', 'Done']),
            'priority' => fake()->randomElement(['Low', 'Medium', 'High']),
            'due_date' => fake()->date(),
            'project_id' => Project::factory(),
            'position' => fake()->numberBetween(1, 10),
        ];
    }
}

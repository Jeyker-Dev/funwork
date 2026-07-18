<?php

namespace Database\Factories;

use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notebook>
 */
class NotebookFactory extends Factory
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
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
            'is_archived' => false,
            'is_shared' => false,
        ];
    }
}

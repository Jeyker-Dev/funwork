<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['folder', 'document', 'home', 'cog', 'user']),
            'type' => 'personal',
            'status' => 'active',
            'owner_id' => \App\Models\User::factory(),
        ];
    }
}

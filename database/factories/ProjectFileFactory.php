<?php

namespace Database\Factories;

use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 */
class ProjectFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'name' => fake()->word() . '.txt',
            'file_path' => 'files/' . fake()->uuid() . '.txt',
            'disk' => 'local',
            'mime_type' => 'text/plain',
            'size' => fake()->randomNumber(4),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}

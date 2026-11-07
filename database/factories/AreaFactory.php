<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'project_id' => Project::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'color' => fake()->randomElement(Area::ALLOWED_COLORS),
            'position' => 1,
        ];
    }
}

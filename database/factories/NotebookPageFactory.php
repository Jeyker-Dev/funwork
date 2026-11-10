<?php

namespace Database\Factories;

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotebookPage>
 */
class NotebookPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notebook_id' => Notebook::factory(),
            'created_by' => User::factory(),
            'order' => 1,
            'content' => [
                'title' => fake()->sentence(3),
                'body' => fake()->paragraph(),
            ],
        ];
    }
}

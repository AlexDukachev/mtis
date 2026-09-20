<?php

namespace Database\Factories;

use App\Models\RoadmapItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadmapItem>
 */
class RoadmapItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4), 'deadline' => now()->addMonths(6)->toDateString(),
            'duration_days' => 60, 'required_people' => 3, 'status' => 'planned',
        ];
    }
}

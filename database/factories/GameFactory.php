<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_hu' => Str::title($name),
            'name_en' => Str::title($name),
            'description_hu' => 'Teszt játék leírás.',
            'description_en' => 'Test game description.',
            'icon' => null,
            'has_question' => true,
            'prompt_hu' => '',
            'prompt_en' => '',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 10,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}

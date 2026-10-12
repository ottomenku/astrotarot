<?php

namespace Database\Factories;

use App\Models\MeaningEntry;
use App\Models\MeaningGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeaningEntry>
 */
class MeaningEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meaning_group_id' => MeaningGroup::factory(),
            'figure_id' => fake()->unique()->slug(2),
            'sort_order' => 0,
            'name_hu' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'meaning_hu' => 'jelentés',
            'meaning_en' => 'meaning',
            'description_hu' => 'Rövid leírás.',
            'description_en' => 'Short description.',
        ];
    }
}

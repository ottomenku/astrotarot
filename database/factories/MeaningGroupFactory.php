<?php

namespace Database\Factories;

use App\Models\CardPack;
use App\Models\MeaningGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MeaningGroup>
 */
class MeaningGroupFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_hu' => Str::title($name),
            'name_en' => Str::title($name),
            'description_hu' => 'Teszt jelentéscsoport.',
            'description_en' => 'Test meaning group.',
            'type' => CardPack::TYPE_TAROT,
            'owner_id' => null,
        ];
    }

    public function oracle(): static
    {
        return $this->state(fn () => [
            'type' => CardPack::TYPE_ORACLE,
        ]);
    }
}

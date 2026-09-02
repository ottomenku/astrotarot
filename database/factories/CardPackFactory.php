<?php

namespace Database\Factories;

use App\Models\CardPack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CardPack>
 */
class CardPackFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_hu' => Str::title($name),
            'name_en' => Str::title($name),
            'description_hu' => 'Rövid teszt leírás.',
            'description_en' => 'Short test description.',
            'type' => CardPack::TYPE_TAROT,
            'polarity' => null,
            'card_count' => 22,
            'style' => 'test',
            'owner' => 'AstroTarot',
            'preview_path' => null,
            'asset_path' => 'card-pack/tarot',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
        ];
    }

    public function oracle(): static
    {
        return $this->state(fn () => [
            'type' => CardPack::TYPE_ORACLE,
            'asset_path' => 'card-pack/egi-orakulum',
        ]);
    }

    public function cardBack(): static
    {
        return $this->state(fn () => [
            'type' => CardPack::TYPE_CARD_BACK,
            'card_count' => 1,
            'asset_path' => 'card-pack/tarot/card-back/card-back.png',
        ]);
    }

    public function background(string $polarity = CardPack::POLARITY_LIGHT): static
    {
        $file = $polarity === CardPack::POLARITY_SHADOW
            ? 'card-pack/tarot/backgrounds/shared-shadow.png'
            : 'card-pack/tarot/backgrounds/shared-light.png';

        return $this->state(fn () => [
            'type' => CardPack::TYPE_BACKGROUND,
            'polarity' => $polarity,
            'card_count' => 1,
            'asset_path' => $file,
        ]);
    }
}

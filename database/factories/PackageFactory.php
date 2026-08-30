<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'icon' => '',
            'name_hu' => Str::title($name),
            'name_en' => Str::title($name),
            'price_huf' => 1900,
            'price_usd' => 5.99,
            'period' => Package::PERIOD_MONTHLY,
            'credits' => 40,
            'body_hu' => '<p>Teszt csomag leírás.</p>',
            'body_en' => '<p>Test package description.</p>',
            'perk_keys' => [],
            'is_active' => true,
            'sort_order' => 10,
            'can_export_pdf' => false,
            'can_view_history' => false,
            'has_astromotto' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => [
            'period' => Package::PERIOD_YEARLY,
        ]);
    }
}

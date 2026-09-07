<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'user_id' => User::factory()->admin(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('##'),
            'title_hu' => $title,
            'title_en' => $title,
            'excerpt_hu' => fake()->sentence(12),
            'excerpt_en' => fake()->sentence(12),
            'body_hu' => '<p>'.fake()->paragraphs(2, true).'</p>',
            'body_en' => '<p>'.fake()->paragraphs(2, true).'</p>',
            'cover_path' => null,
            'published_at' => now()->subHour(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'published_at' => null,
        ]);
    }
}

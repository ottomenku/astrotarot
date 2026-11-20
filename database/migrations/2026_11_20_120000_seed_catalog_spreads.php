<?php

use App\Models\CardPack;
use App\Models\Game;
use App\Models\GameRule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        /** @var list<array<string, mixed>> $spreads */
        $spreads = require database_path('data/catalog_spreads.php');
        $tarotSlug = CardPack::defaultFor(CardPack::TYPE_TAROT)?->slug ?? 'tarot';
        $oracleSlug = CardPack::defaultFor(CardPack::TYPE_ORACLE)?->slug ?? 'egi-orakulum';
        $default = null;

        foreach ($spreads as $spread) {
            $game = Game::query()->updateOrCreate(
                ['slug' => $spread['slug']],
                [
                    'name_hu' => $spread['name_hu'],
                    'name_en' => $spread['name_en'],
                    'description_hu' => $spread['description_hu'],
                    'description_en' => $spread['description_en'],
                    'icon' => null,
                    'has_question' => true,
                    'prompt_hu' => $spread['prompt_hu'],
                    'prompt_en' => $spread['prompt_en'],
                    'light_shadow_mode' => Game::LIGHT_SHADOW_FULL_RANDOM,
                    'astro_data_modes' => $spread['astro'],
                    'spread_types' => $spread['categories'],
                    'spread_dimensions' => $spread['dimensions'],
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => $spread['sort_order'],
                ],
            );

            $game->rules()->delete();

            foreach ($spread['slots'] as $slot) {
                $type = $slot['deck'] === 'oracle' ? CardPack::TYPE_ORACLE : CardPack::TYPE_TAROT;
                GameRule::query()->create([
                    'game_id' => $game->id,
                    'pack_slug' => $type === CardPack::TYPE_ORACLE ? $oracleSlug : $tarotSlug,
                    'pack_type' => $type,
                    'default_pack_id' => null,
                    'title_hu' => $slot['title_hu'],
                    'title_en' => $slot['title_en'],
                    'slot_key' => 'd'.$slot['draw'],
                    'description_hu' => $slot['description_hu'],
                    'description_en' => $slot['description_en'],
                    'card_back_id' => null,
                    'info_mode' => GameRule::INFO_MEANING,
                    'celestial_helper' => $slot['helper'],
                    'light_shadow' => $slot['light'],
                    'row' => $slot['row'],
                    'place' => $slot['place'],
                    'draw_order' => $slot['draw'],
                ]);
            }

            if ($spread['is_default'] === true) {
                $default = $game;
            }
        }

        $default?->makeDefault();
    }

    public function down(): void
    {
        /** @var list<array<string, mixed>> $spreads */
        $spreads = require database_path('data/catalog_spreads.php');
        $slugs = array_column($spreads, 'slug');

        Game::query()->whereIn('slug', $slugs)->each(function (Game $game): void {
            $game->rules()->delete();
            $game->delete();
        });

        Game::query()->where('slug', 'astro-tarot')->first()?->makeDefault();
    }
};

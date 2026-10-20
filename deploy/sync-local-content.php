<?php

/**
 * Copy the local "teszt" spread, attach the gypsy pack to its meaning group,
 * and store the current system and base prompts.
 *
 * Run on the server after migrate: php deploy/sync-local-content.php
 */

use App\Models\CardPack;
use App\Models\Game;
use App\Models\MeaningGroup;
use App\Services\AppSettings;
use App\Services\PackCardImporter;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$settings = app(AppSettings::class);
$settings->putMany([
    AppSettings::SYSTEM_PROMPT_HU => $settings->defaultSystemPromptHu(),
    AppSettings::SYSTEM_PROMPT_EN => $settings->defaultSystemPromptEn(),
    AppSettings::BASE_PROMPT_HU => $settings->defaultBasePromptHu(),
    AppSettings::BASE_PROMPT_EN => $settings->defaultBasePromptEn(),
]);
echo "prompts stored\n";

$game = Game::query()->updateOrCreate(
    ['slug' => 'teszt'],
    [
        'name_hu' => 'teszt',
        'name_en' => 'teszt',
        'description_hu' => 'ez csak egy teszt 3 lapot klell választani 2 oszlopban',
        'description_en' => 'ez csak egy teszt 3 lapot klell választani 2 oszlopban angolul',
        'icon' => null,
        'has_question' => true,
        'prompt_hu' => '',
        'prompt_en' => '',
        'is_active' => true,
        'sort_order' => 3,
    ],
);

Game::query()->whereKeyNot($game->id)->update(['is_default' => false]);
$game->update(['is_default' => true]);

$slots = [
    [
        'slot_key' => 'past',
        'pack_slug' => 'tarot',
        'back_slug' => 'cigany-kartya-back',
        'title_hu' => 'mult',
        'title_en' => 'past',
        'description_hu' => 'a multad',
        'description_en' => 'your past',
        'info_mode' => 'meaning',
        'row' => 1,
        'place' => 1,
        'draw_order' => 2,
    ],
    [
        'slot_key' => 'future',
        'pack_slug' => 'anime-tarot',
        'back_slug' => 'anime-tarot-back',
        'title_hu' => 'jövő',
        'title_en' => 'future',
        'description_hu' => 'a jövőd',
        'description_en' => 'your future',
        'info_mode' => 'meaning',
        'row' => 1,
        'place' => 2,
        'draw_order' => 3,
    ],
    [
        'slot_key' => 'you',
        'pack_slug' => 'egi-orakulum',
        'back_slug' => null,
        'title_hu' => 'te',
        'title_en' => 'you',
        'description_hu' => 'ez vagy te',
        'description_en' => 'yu are',
        'info_mode' => 'astro',
        'row' => 2,
        'place' => 1,
        'draw_order' => 1,
    ],
];

$game->rules()->delete();
foreach ($slots as $slot) {
    $pack = CardPack::query()->where('slug', $slot['pack_slug'])->first();
    $back = $slot['back_slug']
        ? CardPack::query()->where('slug', $slot['back_slug'])->first()
        : null;
    $game->rules()->create([
        'pack_slug' => $slot['pack_slug'],
        'pack_type' => $pack?->type ?? ($slot['pack_slug'] === 'egi-orakulum' ? 'oracle' : 'tarot'),
        'default_pack_id' => $pack?->id,
        'title_hu' => $slot['title_hu'],
        'title_en' => $slot['title_en'],
        'slot_key' => $slot['slot_key'],
        'description_hu' => $slot['description_hu'],
        'description_en' => $slot['description_en'],
        'card_back_id' => $back?->id,
        'info_mode' => $slot['info_mode'],
        'row' => $slot['row'],
        'place' => $slot['place'],
        'draw_order' => $slot['draw_order'],
    ]);
    echo "slot {$slot['slot_key']} pack=".($pack->slug ?? 'MISSING')." back=".($back->slug ?? '-')."\n";
}

$group = MeaningGroup::query()->where('slug', 'ciganykartya')->first();
if ($group !== null) {
    $updated = CardPack::query()->where('slug', 'cigany-kartya')->update(['meaning_group_id' => $group->id]);
    echo "cigany-kartya meaning_group={$group->id} updated={$updated}\n";
} else {
    echo "ciganykartya meaning group missing\n";
}

$imported = app(PackCardImporter::class)->importMissing();
echo "pack_cards imported={$imported}\n";
echo "game={$game->slug} id={$game->id} default=1 rules=".$game->rules()->count()."\n";

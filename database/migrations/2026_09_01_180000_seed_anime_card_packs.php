<?php

use App\Models\CardPack;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $backs = [
            'anime-orakulum' => [
                'name_hu' => 'Anime orákulum hátlap',
                'name_en' => 'Anime oracle back',
                'file' => 'card-pack/anime-orakulum/card-back/card-back.png',
            ],
            'anime-tarot' => [
                'name_hu' => 'Anime tarot hátlap',
                'name_en' => 'Anime tarot back',
                'file' => 'card-pack/anime-tarot/card-back/card-back.png',
            ],
        ];

        $backIds = [];
        $sort = (int) DB::table('card_packs')->max('sort_order') + 10;
        foreach ($backs as $folder => $meta) {
            if (! is_file(public_path($meta['file']))) {
                continue;
            }
            $slug = $folder.'-back';
            $existing = DB::table('card_packs')->where('slug', $slug)->value('id');
            if ($existing) {
                $backIds[$folder] = (int) $existing;

                continue;
            }
            $backIds[$folder] = DB::table('card_packs')->insertGetId([
                'slug' => $slug,
                'name_hu' => $meta['name_hu'],
                'name_en' => $meta['name_en'],
                'description_hu' => 'Anime stílusú hátlap.',
                'description_en' => 'Anime-style card back.',
                'type' => CardPack::TYPE_CARD_BACK,
                'polarity' => null,
                'card_count' => 1,
                'style' => 'anime-fullbleed',
                'owner' => 'AstroTarot',
                'preview_path' => $meta['file'],
                'asset_path' => $meta['file'],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sort += 10;
        }

        $figures = [
            'anime-orakulum' => [
                'type' => CardPack::TYPE_ORACLE,
                'name_hu' => 'Anime orákulum',
                'name_en' => 'Anime oracle',
                'description_hu' => 'Anime stílusú égi mesterek. A pozitív és negatív polaritás a lapon van, külön háttér nélkül.',
                'description_en' => 'Anime celestial masters. Light and shadow polarity is painted into the card; there is no separate background.',
            ],
            'anime-tarot' => [
                'type' => CardPack::TYPE_TAROT,
                'name_hu' => 'Anime tarot',
                'name_en' => 'Anime tarot',
                'description_hu' => '22 nagy arkánum anime stílusban. A pozitív és negatív polaritás a lapon van, külön háttér nélkül.',
                'description_en' => '22 major arcana in anime style. Light and shadow polarity is painted into the card; there is no separate background.',
            ],
        ];

        foreach ($figures as $slug => $meta) {
            if (DB::table('card_packs')->where('slug', $slug)->exists()) {
                continue;
            }
            $deckPath = public_path("card-pack/{$slug}/deck.json");
            $deck = is_file($deckPath)
                ? json_decode((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($deckPath)), true)
                : [];
            $count = is_array($deck['figures'] ?? null) ? count($deck['figures']) : 0;
            $firstId = (string) (($deck['figures'][0]['id'] ?? ''));
            $preview = $firstId !== '' && is_file(public_path("card-pack/{$slug}/figures/{$firstId}-light.png"))
                ? "card-pack/{$slug}/figures/{$firstId}-light.png"
                : null;

            DB::table('card_packs')->insert([
                'slug' => $slug,
                'name_hu' => $meta['name_hu'],
                'name_en' => $meta['name_en'],
                'description_hu' => $meta['description_hu'],
                'description_en' => $meta['description_en'],
                'type' => $meta['type'],
                'polarity' => null,
                'card_count' => $count,
                'style' => 'anime-fullbleed',
                'owner' => 'AstroTarot',
                'preview_path' => $preview,
                'asset_path' => 'card-pack/'.$slug,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $sort,
                'default_card_back_id' => $backIds[$slug] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sort += 10;
        }
    }

    public function down(): void
    {
        DB::table('card_packs')->whereIn('slug', [
            'anime-orakulum',
            'anime-tarot',
            'anime-orakulum-back',
            'anime-tarot-back',
        ])->delete();
    }
};

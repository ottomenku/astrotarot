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
            'cosplay-orakulum' => [
                'name_hu' => 'Cosplay orákulum hátlap',
                'name_en' => 'Cosplay oracle back',
                'file' => 'card-pack/cosplay-orakulum/card-back/card-back.png',
            ],
            'cosplay-tarot' => [
                'name_hu' => 'Cosplay tarot hátlap',
                'name_en' => 'Cosplay tarot back',
                'file' => 'card-pack/cosplay-tarot/card-back/card-back.png',
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
                'description_hu' => 'Fotorealisztikus cosplay hátlap.',
                'description_en' => 'Photorealistic cosplay card back.',
                'type' => CardPack::TYPE_CARD_BACK,
                'polarity' => null,
                'card_count' => 1,
                'style' => 'photoreal-cosplay',
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
            'cosplay-orakulum' => [
                'type' => CardPack::TYPE_ORACLE,
                'name_hu' => 'Cosplay orákulum',
                'name_en' => 'Cosplay oracle',
                'description_hu' => 'Fotorealisztikus, szexi felnőtt cosplay az égi mesterekről. Egy kép laponként, külön háttér és polaritás nélkül.',
                'description_en' => 'Photorealistic sexy adult cosplay of the celestial masters. One image per card, no separate background or polarity variants.',
            ],
            'cosplay-tarot' => [
                'type' => CardPack::TYPE_TAROT,
                'name_hu' => 'Cosplay tarot',
                'name_en' => 'Cosplay tarot',
                'description_hu' => '22 nagy arkánum fotorealisztikus, szexi felnőtt cosplayben. Egy kép laponként, külön háttér és polaritás nélkül.',
                'description_en' => '22 major arcana as photorealistic sexy adult cosplay. One image per card, no separate background or polarity variants.',
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
            $preview = null;
            foreach (["card-pack/{$slug}/figures/{$firstId}.png", "card-pack/{$slug}/figures/{$firstId}-light.png"] as $file) {
                if ($firstId !== '' && is_file(public_path($file))) {
                    $preview = $file;
                    break;
                }
            }

            DB::table('card_packs')->insert([
                'slug' => $slug,
                'name_hu' => $meta['name_hu'],
                'name_en' => $meta['name_en'],
                'description_hu' => $meta['description_hu'],
                'description_en' => $meta['description_en'],
                'type' => $meta['type'],
                'polarity' => null,
                'card_count' => $count,
                'style' => 'photoreal-cosplay',
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
            'cosplay-orakulum',
            'cosplay-tarot',
            'cosplay-orakulum-back',
            'cosplay-tarot-back',
        ])->delete();
    }
};

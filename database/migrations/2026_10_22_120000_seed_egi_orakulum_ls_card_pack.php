<?php

use App\Models\CardPack;
use App\Services\PackCardImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $slug = 'egi-orakulum-ls';
        $sort = (int) DB::table('card_packs')->max('sort_order') + 10;

        $backId = DB::table('card_packs')->where('slug', $slug.'-back')->value('id');
        $backFile = 'card-pack/'.$slug.'/card-back/card-back.png';
        if (! $backId && is_file(public_path($backFile))) {
            $backId = DB::table('card_packs')->insertGetId([
                'slug' => $slug.'-back',
                'name_hu' => 'Égi Orákulum LS hátlap',
                'name_en' => 'Celestial Oracle LS back',
                'description_hu' => 'A fény és az árnyék egy emblémán.',
                'description_en' => 'Light and shadow in one emblem.',
                'type' => CardPack::TYPE_CARD_BACK,
                'polarity' => null,
                'card_count' => 1,
                'style' => 'celestial-polar',
                'owner' => 'AstroTarot',
                'preview_path' => $backFile,
                'asset_path' => $backFile,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sort += 10;
        }

        if (! DB::table('card_packs')->where('slug', $slug)->exists()) {
            $deckPath = public_path("card-pack/{$slug}/deck.json");
            $deck = is_file($deckPath)
                ? json_decode((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($deckPath)), true)
                : [];
            $count = is_array($deck['figures'] ?? null) ? count($deck['figures']) : 0;
            $firstId = (string) (($deck['figures'][0]['id'] ?? ''));
            $preview = $firstId !== '' && is_file(public_path("card-pack/{$slug}/figures/{$firstId}-light.png"))
                ? "card-pack/{$slug}/figures/{$firstId}-light.png"
                : null;
            $meaningGroupId = Schema::hasColumn('card_packs', 'meaning_group_id')
                ? DB::table('card_packs')->where('slug', 'egi-orakulum')->value('meaning_group_id')
                : null;

            DB::table('card_packs')->insert([
                'slug' => $slug,
                'name_hu' => 'Égi Orákulum LS',
                'name_en' => 'Celestial Oracle LS',
                'description_hu' => 'Az égi mesterek fény és árnyék változata. Ugyanaz a figura, de a tartás, a mimika és a szimbólum a polaritással együtt változik.',
                'description_en' => 'Light and shadow faces of the celestial masters. The same figure, with pose, expression, and symbols changing by polarity.',
                'type' => CardPack::TYPE_ORACLE,
                'polarity' => null,
                'card_count' => $count,
                'style' => 'celestial-polar',
                'owner' => 'AstroTarot',
                'meaning_group_id' => $meaningGroupId,
                'preview_path' => $preview,
                'asset_path' => 'card-pack/'.$slug,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $sort,
                'default_card_back_id' => $backId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pack = CardPack::query()->where('slug', $slug)->first();
        if ($pack !== null && ! $pack->cards()->exists()) {
            app(PackCardImporter::class)->importPack($pack);
        }
    }

    public function down(): void
    {
        DB::table('card_packs')->whereIn('slug', [
            'egi-orakulum-ls',
            'egi-orakulum-ls-back',
        ])->delete();
    }
};

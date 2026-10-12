<?php

use App\Models\CardPack;
use App\Models\Package;
use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_packs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_hu');
            $table->string('name_en');
            $table->text('description_hu')->nullable();
            $table->text('description_en')->nullable();
            $table->string('type', 16);
            $table->string('polarity', 16)->nullable();
            $table->unsignedInteger('card_count')->default(0);
            $table->string('style')->nullable();
            $table->string('owner')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('asset_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('default_card_back_id')->nullable();
            $table->unsignedBigInteger('default_background_light_id')->nullable();
            $table->unsignedBigInteger('default_background_shadow_id')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_default']);
        });

        Schema::table('card_packs', function (Blueprint $table) {
            $table->foreign('default_card_back_id')->references('id')->on('card_packs')->nullOnDelete();
            $table->foreign('default_background_light_id')->references('id')->on('card_packs')->nullOnDelete();
            $table->foreign('default_background_shadow_id')->references('id')->on('card_packs')->nullOnDelete();
        });

        Schema::create('user_pack_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pack_type', 16)->nullable();
            $table->foreignId('figure_pack_id')->nullable()->constrained('card_packs')->cascadeOnDelete();
            $table->string('role', 32);
            $table->foreignId('card_pack_id')->constrained('card_packs')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'role', 'pack_type']);
            $table->index(['user_id', 'role', 'figure_pack_id']);
        });

        Schema::table('game_rules', function (Blueprint $table) {
            $table->string('pack_type', 16)->nullable()->after('pack_slug');
            $table->foreignId('default_pack_id')->nullable()->after('pack_type')->constrained('card_packs')->nullOnDelete();
        });

        $this->seedPacks();
        $this->backfillGameRules();
        $this->seedPackFeatures();
    }

    public function down(): void
    {
        Schema::table('game_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_pack_id');
            $table->dropColumn('pack_type');
        });
        Schema::dropIfExists('user_pack_preferences');
        Schema::table('card_packs', function (Blueprint $table) {
            $table->dropForeign(['default_card_back_id']);
            $table->dropForeign(['default_background_light_id']);
            $table->dropForeign(['default_background_shadow_id']);
        });
        Schema::dropIfExists('card_packs');
    }

    private function seedPacks(): void
    {
        $now = now();
        $backs = [];
        $lights = [];
        $shadows = [];

        $backSources = [
            'egi-orakulum' => ['name_hu' => 'Égi Orákulum hátlap', 'name_en' => 'Celestial Oracle back', 'default' => true],
            'tarot' => ['name_hu' => 'Tarot hátlap', 'name_en' => 'Tarot back', 'default' => false],
            'cigany-kartya' => ['name_hu' => 'Cigánykártya hátlap', 'name_en' => 'Gypsy card back', 'default' => false],
        ];
        $sort = 10;
        foreach ($backSources as $folder => $meta) {
            $file = "card-pack/{$folder}/card-back/card-back.png";
            if (! is_file(public_path($file))) {
                continue;
            }
            $backs[$folder] = DB::table('card_packs')->insertGetId([
                'slug' => $folder.'-back',
                'name_hu' => $meta['name_hu'],
                'name_en' => $meta['name_en'],
                'description_hu' => 'Hátlap ehhez a paklihoz.',
                'description_en' => 'Card back for this pack.',
                'type' => CardPack::TYPE_CARD_BACK,
                'polarity' => null,
                'card_count' => 1,
                'style' => null,
                'owner' => 'AstroTarot',
                'preview_path' => $file,
                'asset_path' => $file,
                'is_active' => true,
                'is_default' => $meta['default'],
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sort += 10;
        }

        $bgSources = [
            'egi-orakulum' => [
                'light' => ['name_hu' => 'Égi Orákulum fény', 'name_en' => 'Celestial Oracle light', 'default' => true],
                'shadow' => ['name_hu' => 'Égi Orákulum árnyék', 'name_en' => 'Celestial Oracle shadow', 'default' => true],
            ],
            'tarot' => [
                'light' => ['name_hu' => 'Tarot fény', 'name_en' => 'Tarot light', 'default' => false],
                'shadow' => ['name_hu' => 'Tarot árnyék', 'name_en' => 'Tarot shadow', 'default' => false],
            ],
        ];
        foreach ($bgSources as $folder => $tones) {
            foreach ($tones as $tone => $meta) {
                $file = "card-pack/{$folder}/backgrounds/shared-{$tone}.png";
                if (! is_file(public_path($file))) {
                    continue;
                }
                $id = DB::table('card_packs')->insertGetId([
                    'slug' => $folder.'-'.$tone,
                    'name_hu' => $meta['name_hu'],
                    'name_en' => $meta['name_en'],
                    'description_hu' => $tone === 'light' ? 'Pozitív (fény) háttér.' : 'Negatív (árnyék) háttér.',
                    'description_en' => $tone === 'light' ? 'Positive (light) background.' : 'Negative (shadow) background.',
                    'type' => 'background',
                    'polarity' => $tone,
                    'card_count' => 1,
                    'style' => null,
                    'owner' => 'AstroTarot',
                    'preview_path' => $file,
                    'asset_path' => $file,
                    'is_active' => true,
                    'is_default' => $meta['default'],
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $sort += 10;
                if ($tone === 'light') {
                    $lights[$folder] = $id;
                } else {
                    $shadows[$folder] = $id;
                }
            }
        }

        $figures = [
            'egi-orakulum' => [
                'type' => CardPack::TYPE_ORACLE,
                'is_default' => true,
                'description_hu' => 'Bolygók és jegyek az égi mesterek paklijában.',
                'description_en' => 'Planets and signs in the celestial masters pack.',
                'sort_order' => 1,
            ],
            'tarot' => [
                'type' => CardPack::TYPE_TAROT,
                'is_default' => true,
                'description_hu' => 'Nagy arkánum és válogatott kis arkánum.',
                'description_en' => 'Major arcana and a selection of minors.',
                'sort_order' => 2,
            ],
            'cigany-kartya' => [
                'type' => CardPack::TYPE_TAROT,
                'is_default' => false,
                'description_hu' => 'Hagyományos 36 lapos cigánykártya, teljes kép a lapon.',
                'description_en' => 'Traditional 36-card gypsy fortune deck with full-bleed faces.',
                'sort_order' => 3,
            ],
        ];

        foreach ($figures as $slug => $meta) {
            $deck = $this->deckJson($slug);
            $name = is_array($deck['name'] ?? null) ? $deck['name'] : [];
            $count = is_array($deck['figures'] ?? null) ? count($deck['figures']) : 0;
            $preview = $this->firstFigurePath($slug, $deck);

            DB::table('card_packs')->insert([
                'slug' => $slug,
                'name_hu' => (string) ($name['hu'] ?? $slug),
                'name_en' => (string) ($name['en'] ?? $slug),
                'description_hu' => $meta['description_hu'],
                'description_en' => $meta['description_en'],
                'type' => $meta['type'],
                'polarity' => null,
                'card_count' => $count,
                'style' => is_string($deck['style'] ?? null) ? $deck['style'] : null,
                'owner' => 'AstroTarot',
                'preview_path' => $preview,
                'asset_path' => 'card-pack/'.$slug,
                'is_active' => true,
                'is_default' => $meta['is_default'],
                'sort_order' => $meta['sort_order'],
                'default_card_back_id' => $backs[$slug] ?? null,
                'default_background_light_id' => $lights[$slug] ?? null,
                'default_background_shadow_id' => $shadows[$slug] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function deckJson(string $slug): array
    {
        $path = public_path("card-pack/{$slug}/deck.json");
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $deck
     */
    private function firstFigurePath(string $slug, array $deck): ?string
    {
        $id = (string) (($deck['figures'][0]['id'] ?? ''));
        if ($id === '') {
            return null;
        }
        foreach ([
            "card-pack/{$slug}/figures/{$id}.png",
            "card-pack/{$slug}/figures/minors/{$id}.png",
        ] as $file) {
            if (is_file(public_path($file))) {
                return $file;
            }
        }

        return null;
    }

    private function backfillGameRules(): void
    {
        $packs = DB::table('card_packs')->whereIn('type', CardPack::FIGURE_TYPES)->pluck('id', 'slug');
        $typeBySlug = [
            'egi-orakulum' => CardPack::TYPE_ORACLE,
            'oracle' => CardPack::TYPE_ORACLE,
            'tarot' => CardPack::TYPE_TAROT,
            'tarot-major' => CardPack::TYPE_TAROT,
            'cigany-kartya' => CardPack::TYPE_TAROT,
            'cigany' => CardPack::TYPE_TAROT,
        ];

        foreach (DB::table('game_rules')->get() as $rule) {
            $slug = (string) $rule->pack_slug;
            $type = $typeBySlug[$slug] ?? CardPack::TYPE_TAROT;
            $packId = $packs[$slug] ?? null;
            DB::table('game_rules')->where('id', $rule->id)->update([
                'pack_type' => $type,
                'default_pack_id' => $packId,
            ]);
        }
    }

    private function seedPackFeatures(): void
    {
        $now = now();
        $keys = [
            FeatureCatalog::PACKS_CHOOSE => 0,
            FeatureCatalog::PACKS_BACKS => 0,
            'packs.backgrounds' => 0,
        ];
        foreach ($keys as $key => $cost) {
            if (DB::table('feature_access')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('feature_access')->insert([
                'key' => $key,
                'label_hu' => PackageBody::perkLabel($key, 'hu'),
                'label_en' => PackageBody::perkLabel($key, 'en'),
                'credit_cost' => $cost,
                'logic' => FeatureCatalog::LOGIC_AND,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $ids = DB::table('feature_access')->whereIn('key', array_keys($keys))->pluck('id', 'key');
        $premiumId = Package::query()->where('slug', 'premium')->value('id');
        $expertId = Package::query()->where('slug', 'expert')->value('id');
        $attach = [];
        foreach (array_keys($keys) as $key) {
            if (! isset($ids[$key])) {
                continue;
            }
            if ($premiumId) {
                $attach[] = ['feature_access_id' => $ids[$key], 'package_id' => $premiumId];
            }
            if ($expertId) {
                $attach[] = ['feature_access_id' => $ids[$key], 'package_id' => $expertId];
            }
        }
        if ($attach !== []) {
            DB::table('feature_access_package')->insert($attach);
        }
    }
};

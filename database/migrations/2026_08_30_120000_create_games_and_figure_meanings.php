<?php

use App\Models\GameRule;
use App\Services\AppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_hu');
            $table->string('name_en');
            $table->text('description_hu')->nullable();
            $table->text('description_en')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('has_question')->default(true);
            $table->longText('prompt_hu')->nullable();
            $table->longText('prompt_en')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('game_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('pack_slug', 64);
            $table->string('title_hu');
            $table->string('title_en');
            $table->string('info_mode', 16)->default(GameRule::INFO_NONE);
            $table->unsignedTinyInteger('row')->default(1);
            $table->unsignedTinyInteger('place')->default(1);
            $table->timestamps();

            $table->unique(['game_id', 'row', 'place']);
        });

        Schema::create('figure_meanings', function (Blueprint $table) {
            $table->id();
            $table->string('pack_slug', 64);
            $table->string('figure_id', 64);
            $table->text('meaning_hu')->nullable();
            $table->text('meaning_en')->nullable();
            $table->string('source', 16)->default('deck');
            $table->timestamps();

            $table->unique(['pack_slug', 'figure_id']);
        });

        $now = now();
        $settings = app(AppSettings::class);

        $gameId = DB::table('games')->insertGetId([
            'slug' => 'astro-tarot',
            'name_hu' => 'Asztro tarot',
            'name_en' => 'Astro Tarot',
            'description_hu' => 'Tedd fel a kérdésed a lenti szövegmezőben, majd válassz a pakliból Égi mestert, és tarot figurát!',
            'description_en' => 'Enter your question in the text field below, then choose a Celestial Master and a tarot figure from the decks!',
            'icon' => null,
            'has_question' => true,
            'prompt_hu' => $settings->defaultSystemPromptHu(),
            'prompt_en' => $settings->defaultSystemPromptEn(),
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('game_rules')->insert([
            [
                'game_id' => $gameId,
                'pack_slug' => 'egi-orakulum',
                'title_hu' => 'Égi Orákulum',
                'title_en' => 'Celestial Oracle',
                'info_mode' => GameRule::INFO_ASTRO,
                'row' => 1,
                'place' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'game_id' => $gameId,
                'pack_slug' => 'tarot',
                'title_hu' => 'Tarot figura',
                'title_en' => 'Tarot figure',
                'info_mode' => GameRule::INFO_MEANING,
                'row' => 1,
                'place' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        foreach (['tarot', 'cigany-kartya', 'egi-orakulum'] as $slug) {
            $path = public_path("card-pack/{$slug}/deck.json");
            if (! is_file($path)) {
                continue;
            }
            $deck = json_decode((string) file_get_contents($path), true);
            if (! is_array($deck)) {
                continue;
            }
            foreach ($deck['figures'] ?? [] as $figure) {
                if (! is_array($figure)) {
                    continue;
                }
                $figureId = trim((string) ($figure['id'] ?? ''));
                $blurb = $figure['blurb'] ?? null;
                $hu = is_array($blurb) ? trim((string) ($blurb['hu'] ?? '')) : trim((string) $blurb);
                $en = is_array($blurb) ? trim((string) ($blurb['en'] ?? '')) : $hu;
                if ($figureId === '' || ($hu === '' && $en === '')) {
                    continue;
                }
                DB::table('figure_meanings')->insert([
                    'pack_slug' => $slug,
                    'figure_id' => $figureId,
                    'meaning_hu' => $hu !== '' ? $hu : $en,
                    'meaning_en' => $en !== '' ? $en : $hu,
                    'source' => 'deck',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('figure_meanings');
        Schema::dropIfExists('game_rules');
        Schema::dropIfExists('games');
    }
};

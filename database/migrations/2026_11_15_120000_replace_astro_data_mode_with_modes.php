<?php

use App\Services\AppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('astro_data_modes')->nullable()->after('astro_data_mode');
        });

        foreach (DB::table('games')->get(['id', 'astro_data_mode']) as $game) {
            $modes = match ((string) $game->astro_data_mode) {
                'now' => ['now'],
                'personal' => ['personal'],
                'relations' => ['relations'],
                'ask' => ['personal', 'relations'],
                default => [],
            };
            DB::table('games')->where('id', $game->id)->update([
                'astro_data_modes' => json_encode($modes),
            ]);
        }

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('astro_data_mode');
        });

        $settings = app(AppSettings::class);
        $updates = [];
        foreach ([
            AppSettings::SYSTEM_PROMPT_HU => $settings->defaultSystemPromptHu(),
            AppSettings::SYSTEM_PROMPT_EN => $settings->defaultSystemPromptEn(),
        ] as $key => $prompt) {
            $stored = (string) $settings->get($key, '');
            if ($stored === '' || str_contains($stored, 'need_data')) {
                $updates[$key] = $prompt;
            }
        }
        if ($updates !== []) {
            $settings->putMany($updates);
        }
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('astro_data_mode', 16)->default('none')->after('light_shadow_mode');
        });

        foreach (DB::table('games')->get(['id', 'astro_data_modes']) as $game) {
            $modes = json_decode((string) $game->astro_data_modes, true);
            $modes = is_array($modes) ? $modes : [];
            $mode = 'none';
            if (in_array('relations', $modes, true)) {
                $mode = 'relations';
            } elseif (in_array('personal', $modes, true)) {
                $mode = 'personal';
            } elseif (in_array('now', $modes, true)) {
                $mode = 'now';
            }
            DB::table('games')->where('id', $game->id)->update([
                'astro_data_mode' => $mode,
            ]);
        }

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('astro_data_modes');
        });
    }
};

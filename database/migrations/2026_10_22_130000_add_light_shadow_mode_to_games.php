<?php

use App\Models\Setting;
use App\Services\AppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('light_shadow_mode', 32)->default('full-random')->after('prompt_en');
        });

        $settings = app(AppSettings::class);
        foreach (['hu' => AppSettings::SYSTEM_PROMPT_HU, 'en' => AppSettings::SYSTEM_PROMPT_EN] as $locale => $key) {
            $row = Setting::query()->where('key', $key)->first();
            if ($row === null) {
                continue;
            }
            $value = (string) $row->value;
            if ($value === '' || str_contains($value, 'light_shadow')) {
                continue;
            }
            $row->update([
                'value' => rtrim($value)."\n".$settings->lightShadowProtocol($locale),
            ]);
        }

        Cache::forget('app.settings.bag');
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('light_shadow_mode');
        });
    }
};

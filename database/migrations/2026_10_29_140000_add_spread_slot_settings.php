<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('astro_data_mode', 16)->default('none')->after('light_shadow_mode');
            $table->json('spread_types')->nullable()->after('astro_data_mode');
        });

        Schema::table('game_rules', function (Blueprint $table) {
            $table->string('celestial_helper', 32)->nullable()->after('info_mode');
            $table->string('light_shadow', 16)->nullable()->after('celestial_helper');
            $table->boolean('show_celestial_helper')->default(false)->after('light_shadow');
            $table->boolean('send_celestial_helper')->default(false)->after('show_celestial_helper');
        });
    }

    public function down(): void
    {
        Schema::table('game_rules', function (Blueprint $table) {
            $table->dropColumn([
                'celestial_helper',
                'light_shadow',
                'show_celestial_helper',
                'send_celestial_helper',
            ]);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['astro_data_mode', 'spread_types']);
        });
    }
};

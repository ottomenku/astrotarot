<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('card_packs') || ! Schema::hasColumn('card_packs', 'default_background_light_id')) {
            return;
        }

        Schema::table('card_packs', function (Blueprint $table) {
            $table->dropForeign(['default_background_light_id']);
            $table->dropForeign(['default_background_shadow_id']);
            $table->dropColumn(['default_background_light_id', 'default_background_shadow_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('card_packs') || Schema::hasColumn('card_packs', 'default_background_light_id')) {
            return;
        }

        Schema::table('card_packs', function (Blueprint $table) {
            $table->unsignedBigInteger('default_background_light_id')->nullable();
            $table->unsignedBigInteger('default_background_shadow_id')->nullable();
        });
    }
};

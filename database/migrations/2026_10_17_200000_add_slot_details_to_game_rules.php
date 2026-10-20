<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rules', function (Blueprint $table) {
            $table->string('slot_key', 64)->nullable()->after('title_en');
            $table->text('description_hu')->nullable()->after('slot_key');
            $table->text('description_en')->nullable()->after('description_hu');
            $table->foreignId('card_back_id')->nullable()->after('description_en')->constrained('card_packs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_back_id');
            $table->dropColumn(['slot_key', 'description_hu', 'description_en']);
        });
    }
};

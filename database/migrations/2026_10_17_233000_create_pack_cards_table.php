<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pack_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_pack_id')->constrained('card_packs')->cascadeOnDelete();
            $table->string('figure_id', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name_hu', 160);
            $table->string('name_en', 160)->nullable();
            $table->string('keyword_hu', 160)->nullable();
            $table->string('keyword_en', 160)->nullable();
            $table->text('description_hu')->nullable();
            $table->text('description_en')->nullable();
            $table->string('kind', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique(['card_pack_id', 'figure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_cards');
    }
};

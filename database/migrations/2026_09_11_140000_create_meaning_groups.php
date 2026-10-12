<?php

use App\Support\BaseMeaningCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meaning_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_hu', 120);
            $table->string('name_en', 120);
            $table->text('description_hu')->nullable();
            $table->text('description_en')->nullable();
            $table->string('type', 20);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meaning_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meaning_group_id')->constrained('meaning_groups')->cascadeOnDelete();
            $table->string('figure_id', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name_hu', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('meaning_hu', 160)->nullable();
            $table->string('meaning_en', 160)->nullable();
            $table->text('description_hu')->nullable();
            $table->text('description_en')->nullable();
            $table->timestamps();
            $table->unique(['meaning_group_id', 'figure_id']);
        });

        Schema::table('card_packs', function (Blueprint $table) {
            if (! Schema::hasColumn('card_packs', 'meaning_group_id')) {
                $table->foreignId('meaning_group_id')
                    ->nullable()
                    ->after('owner')
                    ->constrained('meaning_groups')
                    ->nullOnDelete();
            }
        });

        BaseMeaningCatalog::seed();
    }

    public function down(): void
    {
        Schema::table('card_packs', function (Blueprint $table) {
            if (Schema::hasColumn('card_packs', 'meaning_group_id')) {
                $table->dropConstrainedForeignId('meaning_group_id');
            }
        });
        Schema::dropIfExists('meaning_entries');
        Schema::dropIfExists('meaning_groups');
    }
};

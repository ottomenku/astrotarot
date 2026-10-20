<?php

use App\Services\PackCardImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pack_cards', function (Blueprint $table) {
            $table->foreignId('meaning_entry_id')
                ->nullable()
                ->after('figure_id')
                ->constrained('meaning_entries')
                ->nullOnDelete();
        });

        app(PackCardImporter::class)->importMissing();
    }

    public function down(): void
    {
        Schema::table('pack_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meaning_entry_id');
        });
    }
};

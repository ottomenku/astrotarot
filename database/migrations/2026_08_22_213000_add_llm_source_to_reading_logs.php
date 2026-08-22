<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('reading_logs', 'llm_provider')) {
                $table->string('llm_provider', 32)->nullable()->after('model');
            }
            if (! Schema::hasColumn('reading_logs', 'llm_fallback')) {
                $table->boolean('llm_fallback')->default(false)->after('llm_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reading_logs', function (Blueprint $table) {
            if (Schema::hasColumn('reading_logs', 'llm_fallback')) {
                $table->dropColumn('llm_fallback');
            }
            if (Schema::hasColumn('reading_logs', 'llm_provider')) {
                $table->dropColumn('llm_provider');
            }
        });
    }
};

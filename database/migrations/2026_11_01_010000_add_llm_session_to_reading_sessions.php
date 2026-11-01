<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->string('llm_session_id', 128)->nullable()->after('cards');
            $table->string('llm_reply_id', 128)->nullable()->after('llm_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn(['llm_session_id', 'llm_reply_id']);
        });
    }
};

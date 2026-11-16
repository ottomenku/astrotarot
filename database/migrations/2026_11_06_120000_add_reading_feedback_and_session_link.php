<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_logs', function (Blueprint $table) {
            $table->string('session_public_id', 64)->nullable()->after('user_id')->index();
        });

        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('feedback_rating')->nullable()->after('llm_reply_id');
            $table->string('feedback_verdict', 32)->nullable()->after('feedback_rating');
            $table->text('feedback_comment')->nullable()->after('feedback_verdict');
        });

        DB::table('reading_logs')->orderBy('id')->select('id', 'astro')->each(function (object $row): void {
            $astro = json_decode((string) $row->astro, true);
            $sessionId = is_array($astro) ? trim((string) ($astro['session_id'] ?? '')) : '';
            if ($sessionId === '') {
                return;
            }
            DB::table('reading_logs')->where('id', $row->id)->update([
                'session_public_id' => $sessionId,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn(['feedback_rating', 'feedback_verdict', 'feedback_comment']);
        });

        Schema::table('reading_logs', function (Blueprint $table) {
            $table->dropIndex(['session_public_id']);
            $table->dropColumn('session_public_id');
        });
    }
};

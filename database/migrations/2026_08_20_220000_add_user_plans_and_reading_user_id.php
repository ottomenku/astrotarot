<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'plan')) {
                $table->string('plan', 32)->default('free')->after('credits');
            }
            if (! Schema::hasColumn('users', 'last_daily_credit_at')) {
                $table->timestamp('last_daily_credit_at')->nullable()->after('plan');
            }
            if (! Schema::hasColumn('users', 'plan_started_at')) {
                $table->timestamp('plan_started_at')->nullable()->after('last_daily_credit_at');
            }
        });

        Schema::table('reading_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('reading_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reading_logs', function (Blueprint $table) {
            if (Schema::hasColumn('reading_logs', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['plan_started_at', 'last_daily_credit_at', 'plan'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

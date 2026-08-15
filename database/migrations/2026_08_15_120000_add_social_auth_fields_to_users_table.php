<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('provider', 32)->nullable()->after('password');
                $table->string('provider_id')->nullable()->after('provider');
                $table->unique(['provider', 'provider_id']);
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['provider', 'provider_id']);
                $table->dropColumn(['provider', 'provider_id']);
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        }
    }
};

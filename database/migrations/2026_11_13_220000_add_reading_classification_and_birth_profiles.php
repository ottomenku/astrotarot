<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('question');
            $table->string('dimension', 16)->nullable()->after('category');
        });

        Schema::create('birth_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->dateTime('birth_at');
            $table->string('birth_country', 80)->nullable();
            $table->string('birth_city', 120)->nullable();
            $table->decimal('birth_latitude', 10, 6)->nullable();
            $table->decimal('birth_longitude', 10, 6)->nullable();
            $table->string('introduction', 500)->nullable();
            $table->timestamps();
        });

        DB::table('games')->whereNull('spread_dimensions')->update([
            'spread_dimensions' => json_encode(['REL', 'SOC', 'CAR', 'FIN', 'HLT', 'LEG', 'SPL', 'N/A']),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('birth_profiles');

        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn(['category', 'dimension']);
        });
    }
};

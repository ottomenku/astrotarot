<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('birth_at')->nullable()->after('name');
            $table->string('gender', 16)->nullable()->after('birth_at');
            $table->string('introduction', 200)->nullable()->after('gender');
        });

        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 8)->default('hu');
            $table->string('question', 280);
            $table->json('cards');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reading_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('role', 16);
            $table->string('kind', 32);
            $table->longText('content');
            $table->timestamp('created_at')->nullable();

            $table->unique(['reading_session_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_turns');
        Schema::dropIfExists('reading_sessions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_at', 'gender', 'introduction']);
        });
    }
};

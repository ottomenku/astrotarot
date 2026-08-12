<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('reading_logs', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8)->default('hu');
            $table->string('question', 280);
            $table->json('cards');
            $table->json('astro')->nullable();
            $table->longText('system_prompt');
            $table->longText('user_prompt');
            $table->longText('response')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('error')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_logs');
        Schema::dropIfExists('settings');
    }
};

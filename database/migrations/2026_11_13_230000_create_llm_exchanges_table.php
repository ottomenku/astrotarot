<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reading_log_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('method', 8);
            $table->text('url');
            $table->longText('request_body');
            $table->unsignedSmallInteger('status')->nullable();
            $table->longText('response_body');
            $table->timestamp('created_at')->nullable();

            $table->index(['reading_session_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_exchanges');
    }
};

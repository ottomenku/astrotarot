<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'credits')) {
                $table->unsignedInteger('credits')->default(0)->after('role');
            }
        });

        Schema::create('credit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_key', 64);
            $table->unsignedInteger('credits');
            $table->string('status', 32)->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_requests');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'credits')) {
                $table->dropColumn('credits');
            }
        });
    }
};

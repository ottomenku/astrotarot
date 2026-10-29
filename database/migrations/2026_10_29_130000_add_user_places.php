<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('birth_country', 80)->nullable()->after('birth_at');
            $table->string('birth_city', 80)->nullable()->after('birth_country');
            $table->decimal('birth_latitude', 10, 6)->nullable()->after('birth_city');
            $table->decimal('birth_longitude', 10, 6)->nullable()->after('birth_latitude');
            $table->string('residence_country', 80)->nullable()->after('birth_longitude');
            $table->string('residence_city', 80)->nullable()->after('residence_country');
            $table->decimal('residence_latitude', 10, 6)->nullable()->after('residence_city');
            $table->decimal('residence_longitude', 10, 6)->nullable()->after('residence_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'birth_country',
                'birth_city',
                'birth_latitude',
                'birth_longitude',
                'residence_country',
                'residence_city',
                'residence_latitude',
                'residence_longitude',
            ]);
        });
    }
};

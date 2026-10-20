<?php

use App\Support\GypsyCardMeaningCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        GypsyCardMeaningCatalog::seed();
    }

    public function down(): void
    {
        DB::table('meaning_groups')
            ->where('slug', GypsyCardMeaningCatalog::SLUG)
            ->delete();
    }
};

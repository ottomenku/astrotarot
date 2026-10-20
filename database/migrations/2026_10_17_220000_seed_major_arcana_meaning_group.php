<?php

use App\Support\MajorArcanaMeaningCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        MajorArcanaMeaningCatalog::seed();
    }

    public function down(): void
    {
        DB::table('meaning_groups')
            ->where('slug', MajorArcanaMeaningCatalog::SLUG)
            ->delete();
    }
};

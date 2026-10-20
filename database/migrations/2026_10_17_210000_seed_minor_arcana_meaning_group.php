<?php

use App\Support\MinorArcanaMeaningCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MinorArcanaMeaningCatalog::seed();
    }

    public function down(): void
    {
        Illuminate\Support\Facades\DB::table('meaning_groups')
            ->where('slug', MinorArcanaMeaningCatalog::SLUG)
            ->delete();
    }
};

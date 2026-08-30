<?php

use App\Models\Package;
use App\Support\FeatureCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_access', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->unsignedInteger('credit_cost')->default(0);
            $table->string('logic', 8)->default('and');
            $table->timestamps();
        });

        Schema::create('feature_access_package', function (Blueprint $table) {
            $table->foreignId('feature_access_id')->constrained('feature_access')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->primary(['feature_access_id', 'package_id']);
        });

        $now = now();
        $rows = [
            FeatureCatalog::READING => 1,
            FeatureCatalog::CLARIFY => 0,
            FeatureCatalog::EXPAND => 0,
            FeatureCatalog::PDF => 0,
            FeatureCatalog::EMAIL => 0,
            FeatureCatalog::SHARE => 0,
            FeatureCatalog::HISTORY => 0,
            FeatureCatalog::ASTROMOTTO => 0,
            FeatureCatalog::CREDITS_PAGE => 0,
            FeatureCatalog::CREDITS_REQUEST => 0,
            FeatureCatalog::CREDITS_PROMO => 0,
            FeatureCatalog::ACCOUNT => 0,
        ];

        foreach ($rows as $key => $cost) {
            DB::table('feature_access')->insert([
                'key' => $key,
                'credit_cost' => $cost,
                'logic' => FeatureCatalog::LOGIC_AND,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $ids = DB::table('feature_access')->pluck('id', 'key');
        $premiumId = Package::query()->where('slug', 'premium')->value('id');
        $expertId = Package::query()->where('slug', 'expert')->value('id');

        $attach = [];
        foreach ([FeatureCatalog::PDF, FeatureCatalog::EMAIL, FeatureCatalog::HISTORY] as $key) {
            if ($premiumId && isset($ids[$key])) {
                $attach[] = ['feature_access_id' => $ids[$key], 'package_id' => $premiumId];
            }
            if ($expertId && isset($ids[$key])) {
                $attach[] = ['feature_access_id' => $ids[$key], 'package_id' => $expertId];
            }
        }
        if ($expertId && isset($ids[FeatureCatalog::ASTROMOTTO])) {
            $attach[] = ['feature_access_id' => $ids[FeatureCatalog::ASTROMOTTO], 'package_id' => $expertId];
        }
        if ($attach !== []) {
            DB::table('feature_access_package')->insert($attach);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_access_package');
        Schema::dropIfExists('feature_access');
    }
};

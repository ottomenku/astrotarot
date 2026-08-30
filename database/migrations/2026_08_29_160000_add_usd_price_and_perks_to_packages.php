<?php

use App\Models\FeatureAccess;
use App\Models\Package;
use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('price_usd', 8, 2)->default(0)->after('price_huf');
            $table->json('perk_keys')->nullable()->after('body_en');
        });

        $usd = [
            'free' => 0,
            'premium' => 4.99,
            'expert' => 7.99,
        ];
        $perks = [
            'free' => [],
            'premium' => [
                FeatureCatalog::PDF,
                FeatureCatalog::EMAIL,
                FeatureCatalog::HISTORY,
            ],
            'expert' => [
                FeatureCatalog::PDF,
                FeatureCatalog::EMAIL,
                FeatureCatalog::HISTORY,
                FeatureCatalog::ASTROMOTTO,
            ],
        ];

        foreach (Package::query()->get() as $package) {
            $keys = $perks[$package->slug]
                ?? FeatureAccess::query()
                    ->whereHas('packages', fn ($query) => $query->whereKey($package->id))
                    ->pluck('key')
                    ->intersect(FeatureCatalog::packagePerks())
                    ->values()
                    ->all();

            $customHu = in_array($package->slug, ['premium', 'expert'], true) ? '' : (string) $package->body_hu;
            $customEn = in_array($package->slug, ['premium', 'expert'], true) ? '' : (string) $package->body_en;

            $package->price_usd = $usd[$package->slug] ?? 0;
            $package->perk_keys = $keys;
            $package->body_hu = PackageBody::merge($customHu, PackageBody::items(
                'hu',
                (int) $package->credits,
                (string) $package->period,
                $keys,
            ));
            $package->body_en = PackageBody::merge($customEn, PackageBody::items(
                'en',
                (int) $package->credits,
                (string) $package->period,
                $keys,
            ));
            $package->save();
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price_usd', 'perk_keys']);
        });
    }
};

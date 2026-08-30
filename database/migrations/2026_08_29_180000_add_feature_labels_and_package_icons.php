<?php

use App\Models\FeatureAccess;
use App\Models\Package;
use App\Support\PackageBody;
use App\Support\PackageIcons;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_access', function (Blueprint $table) {
            $table->string('label_hu', 80)->nullable()->after('key');
            $table->string('label_en', 80)->nullable()->after('label_hu');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->string('icon', 16)->default('')->after('slug');
        });

        foreach (FeatureAccess::query()->get() as $rule) {
            $rule->update([
                'label_hu' => $rule->label_hu ?: PackageBody::perkLabel($rule->key, 'hu'),
                'label_en' => $rule->label_en ?: PackageBody::perkLabel($rule->key, 'en'),
            ]);
        }

        foreach (Package::query()->get() as $package) {
            $package->update([
                'icon' => PackageIcons::defaultForSlug((string) $package->slug),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('feature_access', function (Blueprint $table) {
            $table->dropColumn(['label_hu', 'label_en']);
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};

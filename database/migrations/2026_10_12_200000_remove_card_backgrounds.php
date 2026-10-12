<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_pack_preferences')) {
            DB::table('user_pack_preferences')
                ->whereIn('role', ['background_light', 'background_shadow'])
                ->delete();
        }

        if (Schema::hasTable('card_packs') && Schema::hasColumn('card_packs', 'default_background_light_id')) {
            DB::table('card_packs')->update([
                'default_background_light_id' => null,
                'default_background_shadow_id' => null,
            ]);
            DB::table('card_packs')->where('type', 'background')->delete();
        }

        if (Schema::hasTable('feature_access')) {
            $ids = DB::table('feature_access')->where('key', 'packs.backgrounds')->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('feature_access_package')) {
                DB::table('feature_access_package')->whereIn('feature_access_id', $ids)->delete();
            }
            DB::table('feature_access')->where('key', 'packs.backgrounds')->delete();
        }
    }

    public function down(): void
    {
        // Background packs were a separate image layer. They are not restored.
    }
};

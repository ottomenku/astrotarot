<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rules', function (Blueprint $table) {
            $table->unsignedTinyInteger('draw_order')->default(1)->after('place');
        });

        $gameIds = DB::table('game_rules')->distinct()->pluck('game_id');
        foreach ($gameIds as $gameId) {
            $rules = DB::table('game_rules')
                ->where('game_id', $gameId)
                ->orderBy('row')
                ->orderBy('place')
                ->orderBy('id')
                ->get(['id']);
            $order = 1;
            foreach ($rules as $rule) {
                DB::table('game_rules')->where('id', $rule->id)->update(['draw_order' => $order]);
                $order++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('game_rules', function (Blueprint $table) {
            $table->dropColumn('draw_order');
        });
    }
};

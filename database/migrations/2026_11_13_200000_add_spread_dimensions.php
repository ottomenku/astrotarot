<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const LEGACY = [
        'YES_NO' => 'BINARY',
        'DILEMMA' => 'CHOICE',
        'TIMING' => 'TIMING',
        'RELATIONSHIP' => 'RELATION',
        'GENERAL' => 'EXPLORATORY',
    ];

    /** @var list<string> */
    private const CATEGORIES = [
        'BINARY', 'CHOICE', 'TIMING', 'EXPLORATORY', 'FORECAST', 'ANALYSIS', 'RELATION', 'N/A',
    ];

    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('spread_dimensions')->nullable()->after('spread_types');
        });

        foreach (DB::table('games')->whereNotNull('spread_types')->get(['id', 'spread_types']) as $game) {
            $types = json_decode((string) $game->spread_types, true);
            if (! is_array($types)) {
                continue;
            }
            $next = [];
            foreach ($types as $type) {
                $type = (string) $type;
                $mapped = self::LEGACY[$type] ?? (in_array($type, self::CATEGORIES, true) ? $type : null);
                if ($mapped !== null && ! in_array($mapped, $next, true)) {
                    $next[] = $mapped;
                }
            }
            DB::table('games')->where('id', $game->id)->update([
                'spread_types' => $next === [] ? null : json_encode($next, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('spread_dimensions');
        });
    }
};

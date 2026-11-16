<?php

use App\Services\AppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = app(AppSettings::class);
        $updates = [];
        $replacements = [
            AppSettings::BASE_PROMPT_HU => [
                'Az első olvasat egyetlen, folyó szöveg legyen, legfeljebb 10–12 mondat.'."\n".'Kötelező ív:',
                'Ha az üzenetben van KATEGORIA VALASZ blokk, az az első olvasat formája, és felülírja a 10–12 mondatos ívet.'."\n".'Ha nincs KATEGORIA VALASZ, az első olvasat egyetlen folyó szöveg, legfeljebb 10–12 mondat. Kötelező ív:',
            ],
            AppSettings::BASE_PROMPT_EN => [
                'The first reading is one flowing answer of at most 10–12 sentences.'."\n".'Required arc:',
                'When the message contains a CATEGORY ANSWER block, that is the shape of the first reading, and it overrides the 10–12 sentence arc.'."\n".'When there is no CATEGORY ANSWER block, the first reading is one flowing answer of at most 10–12 sentences. Required arc:',
            ],
        ];

        foreach ($replacements as $key => [$from, $to]) {
            $stored = (string) $settings->get($key, '');
            if ($stored !== '' && str_contains($stored, $from) && ! str_contains($stored, $to)) {
                $updates[$key] = str_replace($from, $to, $stored);
            }
        }

        if ($updates !== []) {
            $settings->putMany($updates);
        }
    }

    public function down(): void
    {
    }
};

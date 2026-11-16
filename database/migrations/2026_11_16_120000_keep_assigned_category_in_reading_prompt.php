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
                'Ha a kérdés nem értelmezhető vagy nem egyértelmű, a text legyen pontosan ez, más szöveg nélkül: „Az LLM nem tudja értelmezni, vagy nem egyértelmű a kérdésed. Kérlek pontosítsd vagy küld el a pontosítást üresen ha csak egy általános kifejtést szeretnél…”',
                "A nem-értelmezhető mondatot csak akkor használd, ha a kérdés üres vagy nem emberi szöveg. Ilyenkor a text legyen pontosan ez, más szöveg nélkül: „Az LLM nem tudja értelmezni, vagy nem egyértelmű a kérdésed. Kérlek pontosítsd vagy küld el a pontosítást üresen ha csak egy általános kifejtést szeretnél…”\nHa a kategória már meg van adva, mindig írd meg az olvasatot. Szerencsejáték, pénz, egészség és jog időzítése is olvasat: szimbolikus időszak és óvatosság. Nyereményt, esélyt, tétet vagy biztos kimenetelt ne ígérj, és ne válaszd helyette a nem-értelmezhető mondatot.",
            ],
            AppSettings::BASE_PROMPT_EN => [
                'If the question cannot be interpreted or is ambiguous, the text must be exactly this and nothing else: “The LLM cannot interpret this, or the question is not clear. Please clarify, or send the clarification empty if you only want a general reading…”',
                "Use the cannot-interpret sentence only when the question is empty or not human language. Then the text must be exactly this and nothing else: “The LLM cannot interpret this, or the question is not clear. Please clarify, or send the clarification empty if you only want a general reading…”\nWhen a category is already given, always write the reading. Timing for games of chance, money, health and law is still a reading: a symbolic window and the caution. Do not promise winnings, odds, a stake or a certain outcome, and do not answer with the cannot-interpret sentence instead.",
            ],
            AppSettings::SYSTEM_PROMPT_HU => [
                'Ha nincs égi blokk, csak a lapjelentésből dolgozz.',
                'Ha nincs égi blokk, csak a lapjelentésből dolgozz. Ha az üzenet megadja a kategóriát, a spread_type az legyen.',
            ],
            AppSettings::SYSTEM_PROMPT_EN => [
                'When there is no sky block, work from the card meanings.',
                'When there is no sky block, work from the card meanings. When the message already names a category, spread_type stays that category.',
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

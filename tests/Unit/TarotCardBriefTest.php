<?php

namespace Tests\Unit;

use App\Support\TarotCardBrief;
use Tests\TestCase;

class TarotCardBriefTest extends TestCase
{
    public function test_major_card_lines_use_the_catalog_and_skip_unknown_figures(): void
    {
        $lines = TarotCardBrief::lines('hu', '01-the-magician');

        $this->assertContains('jelentés: tudást kereső ember', $lines);
        $this->assertContains('fény: A tudás eszközei a kézben vannak.', $lines);
        $this->assertNotContains('leírás: Tudást kereső ember.', $lines);
        $this->assertFalse(collect($lines)->contains(fn (string $line): bool => str_starts_with($line, 'élet:')));
        $this->assertSame([], TarotCardBrief::lines('hu', 'mars'));
        $this->assertSame([], TarotCardBrief::lines('hu', 'minor-suits'));
    }

    public function test_english_lines_use_the_english_catalog(): void
    {
        $lines = TarotCardBrief::lines('en', 'cups-ace');

        $this->assertContains('meaning: new emotional beginning', $lines);
        $this->assertFalse(collect($lines)->contains(fn (string $line): bool => str_starts_with($line, 'life:')));
        $this->assertNotContains('katalógus: új érzelmi kezdet', $lines);
    }
}

<?php

namespace Tests\Unit;

use App\Models\CardPack;
use App\Models\FigureMeaning;
use App\Services\FigureMeaningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FigureMeaningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_seeded_tarot_meaning_from_the_base_group(): void
    {
        $service = app(FigureMeaningService::class);

        $text = $service->text('tarot', '01-the-magician', 'hu', 'A Mágus');

        $this->assertNotSame('', $text);
        $this->assertStringContainsString('Mágus', $text);
    }

    public function test_falls_back_to_deck_when_pack_has_no_group(): void
    {
        CardPack::query()->where('slug', 'tarot')->update(['meaning_group_id' => null]);

        $service = app(FigureMeaningService::class);
        $text = $service->text('tarot', '01-the-magician', 'hu', 'A Mágus');

        $this->assertNotSame('', $text);
        $this->assertDatabaseHas('figure_meanings', [
            'pack_slug' => 'tarot',
            'figure_id' => '01-the-magician',
        ]);
        $this->assertSame(FigureMeaning::SOURCE_DECK, FigureMeaning::query()
            ->where('pack_slug', 'tarot')
            ->where('figure_id', '01-the-magician')
            ->value('source'));
    }
}

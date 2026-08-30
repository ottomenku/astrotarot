<?php

namespace Tests\Unit;

use App\Support\CardPackCatalog;
use Tests\TestCase;

class CardPackCatalogTest extends TestCase
{
    public function test_lists_the_three_packs_with_required_folders(): void
    {
        $slugs = CardPackCatalog::slugs();

        $this->assertSame(['cigany-kartya', 'egi-orakulum', 'tarot'], $slugs);
        $this->assertTrue(CardPackCatalog::hasBackgrounds('egi-orakulum'));
        $this->assertTrue(CardPackCatalog::hasBackgrounds('tarot'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('cigany-kartya'));
    }

    public function test_aliases_legacy_deck_keys(): void
    {
        $this->assertSame('egi-orakulum', CardPackCatalog::canonicalize('oracle'));
        $this->assertSame('tarot', CardPackCatalog::canonicalize('tarot-major'));
        $this->assertSame('cigany-kartya', CardPackCatalog::canonicalize('cigany'));
    }

    public function test_resolves_figure_and_background_paths(): void
    {
        $this->assertSame(
            'card-pack/egi-orakulum/figures/venus.png',
            CardPackCatalog::figureRelative('oracle', 'venus')
        );
        $this->assertSame(
            'card-pack/tarot/figures/minors/cups-04.png',
            CardPackCatalog::figureRelative('tarot', 'cups-04')
        );
        $this->assertNotNull(CardPackCatalog::backgroundRelative('egi-orakulum', 'shadow'));
        $this->assertNull(CardPackCatalog::backgroundRelative('cigany-kartya', 'light'));
    }
}

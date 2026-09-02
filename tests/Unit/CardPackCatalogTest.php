<?php

namespace Tests\Unit;

use App\Support\CardPackCatalog;
use Tests\TestCase;

class CardPackCatalogTest extends TestCase
{
    public function test_lists_the_three_packs_with_required_folders(): void
    {
        $slugs = CardPackCatalog::slugs();

        $this->assertContains('cigany-kartya', $slugs);
        $this->assertContains('egi-orakulum', $slugs);
        $this->assertContains('tarot', $slugs);
        $this->assertContains('anime-orakulum', $slugs);
        $this->assertContains('anime-tarot', $slugs);
        $this->assertContains('cosplay-orakulum', $slugs);
        $this->assertContains('cosplay-tarot', $slugs);
        $this->assertContains('tacsko-orakulum', $slugs);
        $this->assertContains('tacsko-tarot', $slugs);
        $this->assertTrue(CardPackCatalog::hasBackgrounds('egi-orakulum'));
        $this->assertTrue(CardPackCatalog::hasBackgrounds('tarot'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('cigany-kartya'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('anime-orakulum'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('anime-tarot'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('cosplay-orakulum'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('cosplay-tarot'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('tacsko-orakulum'));
        $this->assertFalse(CardPackCatalog::hasBackgrounds('tacsko-tarot'));
        $this->assertTrue(CardPackCatalog::hasPolarFigures('anime-orakulum'));
        $this->assertTrue(CardPackCatalog::hasPolarFigures('anime-tarot'));
        $this->assertFalse(CardPackCatalog::hasPolarFigures('cosplay-orakulum'));
        $this->assertFalse(CardPackCatalog::hasPolarFigures('cosplay-tarot'));
        $this->assertFalse(CardPackCatalog::hasPolarFigures('tacsko-orakulum'));
        $this->assertFalse(CardPackCatalog::hasPolarFigures('tacsko-tarot'));
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
        $this->assertSame(
            'card-pack/anime-orakulum/figures/sun-light.png',
            CardPackCatalog::figureRelative('anime-orakulum', 'sun', 'light')
        );
        $this->assertSame(
            'card-pack/anime-orakulum/figures/sun-shadow.png',
            CardPackCatalog::figureRelative('anime-orakulum', 'sun', 'shadow')
        );
        $this->assertSame(
            'card-pack/anime-tarot/figures/00-the-fool-light.png',
            CardPackCatalog::figureRelative('anime-tarot', '00-the-fool', 'positive')
        );
        $this->assertSame(
            'card-pack/cosplay-orakulum/figures/sun.png',
            CardPackCatalog::figureRelative('cosplay-orakulum', 'sun')
        );
        $this->assertSame(
            'card-pack/tacsko-orakulum/figures/sun.png',
            CardPackCatalog::figureRelative('tacsko-orakulum', 'sun')
        );
        $this->assertNull(CardPackCatalog::backgroundRelative('cigany-kartya', 'light'));
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class PromoShareTest extends TestCase
{
    public function test_promo_share_page_exposes_facebook_open_graph_tags(): void
    {
        $this->get(route('promo.share'))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="AstroTarot">', false)
            ->assertSee('og:description', false)
            ->assertSee('bevezető promócióban ajándék prémium csomag', false)
            ->assertSee('og:image', false)
            ->assertSee('og/astrotarot-promo.jpg', false);
    }
}

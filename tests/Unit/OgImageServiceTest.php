<?php

namespace Tests\Unit;

use App\Services\OgImageService;
use Tests\TestCase;

class OgImageServiceTest extends TestCase
{
    public function test_resolves_oracle_major_and_minor_figure_paths(): void
    {
        $service = app(OgImageService::class);

        $this->assertTrue(
            str_ends_with(str_replace('\\', '/', $service->figureAbsolutePath('oracle', 'venus') ?? ''), 'card-pack/egi-orakulum/figures/venus.png')
        );
        $this->assertTrue(
            str_ends_with(str_replace('\\', '/', $service->figureAbsolutePath('tarot', '01-the-magician') ?? ''), 'card-pack/tarot/figures/01-the-magician.png')
        );
        $this->assertTrue(
            str_ends_with(str_replace('\\', '/', $service->figureAbsolutePath('tarot', 'cups-04') ?? ''), 'figures/minors/cups-04.png')
        );
    }

    public function test_missing_figure_returns_null(): void
    {
        $service = app(OgImageService::class);

        $this->assertNull($service->figureAbsolutePath('tarot', ''));
        $this->assertNull($service->figureAbsolutePath('tarot', 'does-not-exist'));
        $this->assertNull($service->figurePublicUrl('oracle', 'nope'));
    }

    public function test_make_two_card_jpeg_when_gd_is_available(): void
    {
        $service = app(OgImageService::class);
        if (! $service->canGenerate()) {
            $this->markTestSkipped('GD is not available.');
        }

        $jpeg = $service->makeTwoCardJpeg([
            ['deck' => 'oracle', 'figure_id' => 'venus', 'variant' => 'shadow'],
            ['deck' => 'tarot', 'figure_id' => 'cups-04', 'variant' => 'shadow'],
        ]);

        $this->assertNotNull($jpeg);
        $this->assertSame("\xFF\xD8", substr($jpeg, 0, 2));
    }

    public function test_make_two_card_jpeg_needs_two_cards(): void
    {
        $this->assertNull(app(OgImageService::class)->makeTwoCardJpeg([
            ['deck' => 'oracle', 'figure_id' => 'venus'],
        ]));
    }
}

<?php

namespace Tests\Unit;

use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use PHPUnit\Framework\TestCase;

class PackageBodyTest extends TestCase
{
    public function test_merge_appends_localized_perk_list_and_replaces_previous_one(): void
    {
        $html = PackageBody::merge(
            '<p>Extra</p><ul class="package-perks"><li>Régi</li></ul>',
            PackageBody::items('hu', 50, 'monthly', [FeatureCatalog::PDF, FeatureCatalog::HISTORY]),
        );

        $this->assertStringContainsString('<p>Extra</p>', $html);
        $this->assertStringContainsString('50 kredit / hó', $html);
        $this->assertStringContainsString('PDF mentés', $html);
        $this->assertStringContainsString('Kérdésnapló', $html);
        $this->assertStringNotContainsString('Régi', $html);

        $en = PackageBody::merge(
            '<p>Extra</p>',
            PackageBody::items('en', 50, 'monthly', [FeatureCatalog::PDF]),
        );
        $this->assertStringContainsString('50 credits / month', $en);
        $this->assertStringContainsString('PDF download', $en);
    }

    public function test_custom_labels_override_defaults(): void
    {
        $items = PackageBody::items('hu', 0, 'monthly', [FeatureCatalog::PDF], [
            FeatureCatalog::PDF => ['hu' => 'PDF kártya', 'en' => 'PDF card'],
        ]);

        $this->assertSame(['PDF kártya'], $items);

        $en = PackageBody::items('en', 0, 'monthly', [FeatureCatalog::PDF], [
            FeatureCatalog::PDF => ['hu' => 'PDF kártya', 'en' => 'PDF card'],
        ]);
        $this->assertSame(['PDF card'], $en);
    }
}

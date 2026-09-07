<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_plan_body_strips_scripts_and_keeps_lists(): void
    {
        $html = HtmlSanitizer::planBody('<ul class="package-perks"><li>PDF</li></ul><script>alert(1)</script><p onclick="x()">ok</p>');

        $this->assertStringContainsString('<ul class="package-perks"><li>PDF</li></ul>', $html);
        $this->assertStringContainsString('<p>ok</p>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_article_body_allows_safe_images_and_strips_scripts(): void
    {
        $html = HtmlSanitizer::articleBody(
            '<p>ok</p><img src="/media/blog-covers/a.jpg" alt="Borító" onclick="x()"><img src="javascript:alert(1)"><script>bad()</script>'
        );

        $this->assertStringContainsString('<p>ok</p>', $html);
        $this->assertStringContainsString('<img src="/media/blog-covers/a.jpg" alt="Borító">', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }
}

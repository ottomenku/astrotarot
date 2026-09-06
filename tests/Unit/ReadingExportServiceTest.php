<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ReadingExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReadingExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_payload_fills_defaults(): void
    {
        $payload = app(ReadingExportService::class)->normalizePayload([
            'question' => ' Mit tegyek? ',
            'cards' => [
                ['figure_id' => 'mars', 'deck' => 'oracle'],
                ['figure_id' => '01-the-magician', 'deck' => 'tarot'],
            ],
            'answers' => [
                ['mode' => 'initial', 'text' => 'Első válasz', 'source' => 'Ollama · qwen2.5:14b'],
                ['mode' => 'clarify', 'text' => 'Pontosítás szövege'],
            ],
        ]);

        $this->assertSame('hu', $payload['locale']);
        $this->assertSame('Mit tegyek?', $payload['question']);
        $this->assertCount(2, $payload['cards']);
        $this->assertSame('Égi mester', $payload['cards'][0]['slot_title']);
        $this->assertSame('Választott figura', $payload['cards'][1]['slot_title']);
        $this->assertSame('Olvasat', $payload['answers'][0]['label']);
        $this->assertSame('Ollama · qwen2.5:14b', $payload['answers'][0]['source']);
        $this->assertSame('Pontosítás', $payload['answers'][1]['label']);
    }

    public function test_normalize_payload_requires_question_two_cards_and_answers(): void
    {
        $service = app(ReadingExportService::class);

        try {
            $service->normalizePayload(['question' => '', 'cards' => [], 'answers' => []]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payload', $e->errors());
        }
    }

    public function test_create_share_stores_token_and_expiry(): void
    {
        $user = User::factory()->create();
        $service = app(ReadingExportService::class);
        $payload = $service->normalizePayload([
            'question' => 'Mit tegyek?',
            'cards' => [
                ['figure_id' => 'mars', 'deck' => 'oracle'],
                ['figure_id' => '01-the-magician', 'deck' => 'tarot'],
            ],
            'answers' => [['text' => 'Válasz']],
        ]);

        $share = $service->createShare($user, $payload);

        $this->assertSame($user->id, $share->user_id);
        $this->assertSame(40, strlen($share->token));
        $this->assertFalse($share->isExpired());
        $this->assertSame('Mit tegyek?', $share->payload['question']);
    }

    public function test_render_html_includes_question_and_answers(): void
    {
        $service = app(ReadingExportService::class);
        $payload = $service->normalizePayload([
            'locale' => 'en',
            'question' => 'What should I do?',
            'cards' => [
                ['figure_id' => 'mars', 'deck' => 'oracle', 'figure_name' => 'Mars'],
                ['figure_id' => '01-the-magician', 'deck' => 'tarot', 'figure_name' => 'The Magician'],
            ],
            'answers' => [['mode' => 'expand', 'text' => 'Go deeper.']],
        ]);

        $html = $service->renderHtml($payload);

        $this->assertStringContainsString('What should I do?', $html);
        $this->assertStringContainsString('Go deeper.', $html);
        $this->assertSame('Deeper reading', $payload['answers'][0]['label']);
        if (! extension_loaded('gd')) {
            $this->assertNull($payload['cards'][0]['image_src']);

            return;
        }
        $this->assertNotEmpty($payload['cards'][0]['image_src']);
        $this->assertNotEmpty($payload['cards'][1]['image_src']);
        $this->assertStringContainsString('class="card-img"', $html);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $payload['cards'][0]['image_src']);
    }

    public function test_pdf_embeds_card_images(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to embed card images in PDFs.');
        }
        $service = app(ReadingExportService::class);
        $payload = $service->normalizePayload([
            'question' => 'Mit tegyek?',
            'cards' => [
                ['figure_id' => 'mars', 'deck' => 'oracle', 'figure_name' => 'Mars'],
                ['figure_id' => '01-the-magician', 'deck' => 'tarot', 'variant' => 'shadow', 'figure_name' => 'A Mágus'],
            ],
            'answers' => [['text' => 'Válasz']],
        ]);

        $pdf = $service->makePdf($payload);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertTrue(
            (bool) preg_match('/\/Subtype\s*\/Image/', $pdf),
            'PDF should embed card images as XObjects'
        );
    }

    public function test_pdf_embeds_fullbleed_pack_images(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to embed card images in PDFs.');
        }
        $service = app(ReadingExportService::class);
        $payload = $service->normalizePayload([
            'question' => 'Mit tegyek?',
            'cards' => [
                ['figure_id' => 'sun', 'deck' => 'tacsko-orakulum', 'figure_name' => 'Nap'],
                ['figure_id' => '00-the-fool', 'deck' => 'tacsko-tarot', 'figure_name' => 'A Bolond'],
            ],
            'answers' => [['text' => 'Válasz']],
        ]);

        $this->assertNotEmpty($payload['cards'][0]['image_src']);
        $this->assertNotEmpty($payload['cards'][1]['image_src']);

        $pdf = $service->makePdf($payload);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertTrue(
            (bool) preg_match('/\/Subtype\s*\/Image/', $pdf),
            'PDF should embed full-bleed pack images as XObjects'
        );
    }
}

<?php

namespace Tests\Feature;

use App\Mail\ReadingExportMail;
use App\Models\ReadingShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReadingExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'locale' => 'hu',
            'cards' => [
                [
                    'figure_id' => 'mars',
                    'figure_name' => 'Mars',
                    'keyword' => 'akarat',
                    'blurb' => '',
                    'variant' => 'positive',
                    'polarity' => 'positive',
                    'deck' => 'oracle',
                    'slot_title' => 'Választott Égi mestered',
                    'astro_caption' => 'Mars · Skorpió',
                ],
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
                    'keyword' => 'teremtő erő',
                    'blurb' => 'A Mágus tudatos alkotóerőt hoz.',
                    'variant' => 'shadow',
                    'polarity' => 'shadow',
                    'deck' => 'tarot',
                    'slot_title' => 'Választott Figurád',
                    'astro_caption' => 'A Mágus — alkotóerő',
                ],
            ],
            'answers' => [
                [
                    'mode' => 'initial',
                    'label' => 'Olvasat',
                    'text' => 'Az első válasz szövege.',
                ],
                [
                    'mode' => 'expand',
                    'label' => 'Bővebb kifejtés',
                    'text' => 'A második, bővebb válasz.',
                ],
            ],
        ];
    }

    public function test_authenticated_user_can_download_pdf(): void
    {
        $user = User::factory()->create(['credits' => 10, 'plan' => 'premium']);

        $response = $this->actingAs($user)
            ->post(route('reading.export.pdf'), $this->payload());

        $response->assertOk();
        $this->assertStringContainsString('%PDF', $response->getContent());
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_authenticated_user_can_email_reading(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'credits' => 10,
            'plan' => 'premium',
            'email' => 'olvaso@example.com',
        ]);

        $this->actingAs($user)
            ->postJson(route('reading.export.email'), $this->payload())
            ->assertOk()
            ->assertJsonPath('email', 'olvaso@example.com');

        Mail::assertSent(ReadingExportMail::class, function (ReadingExportMail $mail) {
            return $mail->hasTo('olvaso@example.com');
        });
    }

    public function test_facebook_share_creates_public_page(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAs($user)
            ->postJson(route('reading.share.create'), $this->payload())
            ->assertOk();

        $url = $response->json('url');
        $this->assertNotEmpty($url);
        $this->assertDatabaseCount('reading_shares', 1);

        $token = ReadingShare::query()->first()->token;
        $this->get(route('reading.share.show', $token))
            ->assertOk()
            ->assertSee('Mit tegyek most a kapcsolatommal?', false)
            ->assertSee('Az első válasz szövege.', false)
            ->assertSee('A második, bővebb válasz.', false)
            ->assertSee('Mars', false)
            ->assertSee('og:image', false)
            ->assertSee('og:title', false)
            ->assertSee(route('reading.share.og', $token), false);
    }

    public function test_expired_share_is_gone(): void
    {
        $user = User::factory()->create();
        $share = ReadingShare::query()->create([
            'user_id' => $user->id,
            'token' => str_repeat('a', 40),
            'payload' => $this->payload(),
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('reading.share.show', $share->token))->assertStatus(410);
        $this->get(route('reading.share.og', $share->token))->assertStatus(410);
    }
}

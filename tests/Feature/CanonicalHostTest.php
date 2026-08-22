<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://astrotarot.hu']);
    }

    public function test_www_host_redirects_to_canonical_url(): void
    {
        $this->get('https://www.astrotarot.hu/')
            ->assertRedirect('https://astrotarot.hu/');
    }

    public function test_alias_host_redirects_to_canonical_url(): void
    {
        $this->get('https://astrotarot.cloud/credits')
            ->assertRedirect('https://astrotarot.hu/credits');
    }

    public function test_canonical_host_is_not_redirected(): void
    {
        $this->get('https://astrotarot.hu/')
            ->assertOk();
    }

    public function test_acme_challenge_is_not_redirected(): void
    {
        $response = $this->get('https://www.astrotarot.hu/.well-known/acme-challenge/test-token');

        $this->assertNotSame(301, $response->getStatusCode());
    }

    public function test_unsafe_methods_are_not_redirected(): void
    {
        $this->postJson('https://www.astrotarot.hu/reading', [])
            ->assertUnauthorized();
    }
}

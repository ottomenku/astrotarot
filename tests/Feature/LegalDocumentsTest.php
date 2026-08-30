<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_pages_show_sample_text(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('MINTA — Általános Szerződési Feltételek', false)
            ->assertSee(route('legal.contact'), false)
            ->assertSee(route('legal.privacy'), false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('MINTA — Adatkezelési tájékoztató', false);
    }

    public function test_contact_page_shows_email_and_footer_links(): void
    {
        $this->get(route('legal.contact'))
            ->assertOk()
            ->assertSee(__('messages.contact_title'), false)
            ->assertSee('hello@astrotarot.hu', false)
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false);
    }

    public function test_landing_footer_links_contact_and_legal_pages(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.contact'), false)
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(__('messages.footer_contact'), false)
            ->assertSee(__('messages.footer_terms'), false);
    }

    public function test_home_register_form_links_to_legal_pages(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee('name="terms"', false)
            ->assertSee('name="privacy"', false);
    }

    public function test_contact_is_reachable_when_registration_is_closed(): void
    {
        app(AppSettings::class)->putMany([
            AppSettings::REGISTRATION_ENABLED => '0',
        ]);

        $this->get(route('legal.contact'))->assertOk();
        $this->get(route('legal.terms'))->assertOk();
    }

    public function test_social_legal_page_requires_pending_session(): void
    {
        $this->get(route('register.legal'))->assertRedirect(route('login'));
        $this->post(route('register.legal.store'), [
            'terms' => '1',
            'privacy' => '1',
        ])->assertRedirect(route('login'));
    }

    public function test_admin_legal_page_shows_defaults(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.legal.edit'))
            ->assertOk()
            ->assertSee(__('admin.legal.title'), false)
            ->assertSee(app(AppSettings::class)->defaultTermsHu(), false);
    }
}

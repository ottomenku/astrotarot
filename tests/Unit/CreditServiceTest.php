<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_bonus_adds_credits_and_keeps_free_plan(): void
    {
        $user = User::factory()->create(['credits' => 0, 'plan' => CreditService::PLAN_FREE]);

        app(CreditService::class)->grantSignupBonus($user->fresh());

        $user->refresh();
        $this->assertSame(10, (int) $user->credits);
        $this->assertSame(CreditService::PLAN_FREE, $user->plan);
    }

    public function test_spend_for_question_debits_one_credit(): void
    {
        $user = User::factory()->create(['credits' => 4]);

        $balance = app(CreditService::class)->spendForQuestion($user);

        $this->assertSame(3, $balance);
        $this->assertSame(3, (int) $user->fresh()->credits);
    }

    public function test_spend_for_question_fails_without_credits(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        $this->expectException(RuntimeException::class);
        app(CreditService::class)->spendForQuestion($user);
    }

    public function test_daily_login_bonus_is_skipped_for_premium(): void
    {
        $user = User::factory()->create([
            'credits' => 20,
            'plan' => CreditService::PLAN_PREMIUM,
            'last_daily_credit_at' => null,
        ]);

        $balance = app(CreditService::class)->grantDailyLoginBonus($user);

        $this->assertSame(20, $balance);
        $this->assertNull($user->fresh()->last_daily_credit_at);
    }

    public function test_plan_gates_pdf_history_and_astromotto(): void
    {
        $service = app(CreditService::class);
        $free = User::factory()->create(['plan' => 'free']);
        $premium = User::factory()->create(['plan' => 'premium']);
        $expert = User::factory()->create(['plan' => 'expert']);

        $this->assertFalse($service->canExportPdf($free));
        $this->assertFalse($service->canViewHistory($free));
        $this->assertFalse($service->hasAstroMottoAccess($free));

        $this->assertTrue($service->canExportPdf($premium));
        $this->assertTrue($service->canViewHistory($premium));
        $this->assertFalse($service->hasAstroMottoAccess($premium));

        $this->assertTrue($service->canExportPdf($expert));
        $this->assertTrue($service->hasAstroMottoAccess($expert));
    }

    public function test_unknown_package_and_plan_return_null(): void
    {
        $service = app(CreditService::class);

        $this->assertNull($service->package('pack_10'));
        $this->assertNotNull($service->package('pack_50'));
        $this->assertNull($service->plan('gold'));
        $this->assertSame('premium', $service->plan('premium')?->slug);
    }

    public function test_activate_promo_rejects_free_plan(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $this->expectException(RuntimeException::class);
        app(CreditService::class)->activatePromoPlan($user, CreditService::PLAN_FREE);
    }
}

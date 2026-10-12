<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use App\Support\FeatureCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditService
{
    public const SIGNUP_BONUS = 10;

    public const DAILY_LOGIN_BONUS = 1;

    public const QUESTION_COST = 1;

    public const PLAN_FREE = 'free';

    public const PLAN_PREMIUM = 'premium';

    public const PLAN_EXPERT = 'expert';

    public const REQUEST_PACK_KEY = 'pack_50';

    public const REQUEST_PACK_CREDITS = 50;

    public const ADMIN_MONTHLY_CREDITS = 1000;

    public const ASTROMOTTO_URL = 'https://astromotto.hu';

    /**
     * Credit top-up request options (only one).
     *
     * @return list<array{key: string, credits: int, label_hu: string, label_en: string}>
     */
    public function packages(): array
    {
        return [
            [
                'key' => self::REQUEST_PACK_KEY,
                'credits' => self::REQUEST_PACK_CREDITS,
                'label_hu' => '50 kredit igénylés',
                'label_en' => 'Request 50 credits',
            ],
        ];
    }

    public function package(string $key): ?array
    {
        return $this->findByKey($this->packages(), $key);
    }

    /**
     * Subscription-style plans shown on the packages page.
     *
     * @return list<Package>
     */
    public function plans(): array
    {
        return Package::query()->active()->ordered()->get()->all();
    }

    public function plan(string $key): ?Package
    {
        return Package::query()->where('slug', $key)->first();
    }

    public function grantSignupBonus(User $user): void
    {
        $user->forceFill([
            'credits' => (int) $user->credits + self::SIGNUP_BONUS,
            'plan' => $user->plan ?: self::PLAN_FREE,
        ])->save();
    }

    /**
     * Grant +1 credit once per calendar day for Free plan users.
     */
    public function grantDailyLoginBonus(User $user): int
    {
        return (int) DB::transaction(function () use ($user) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->isAdmin()) {
                return $this->refillAdminMonthlyCredits($locked);
            }
            $plan = $locked->plan ?: self::PLAN_FREE;
            $package = $this->plan($plan);

            if ($package !== null && ! $package->isFree()) {
                return (int) $locked->credits;
            }

            if ($package === null && $plan !== self::PLAN_FREE) {
                return (int) $locked->credits;
            }

            $today = now()->startOfDay();
            if ($locked->last_daily_credit_at && $locked->last_daily_credit_at->gte($today)) {
                return (int) $locked->credits;
            }

            $locked->credits = (int) $locked->credits + self::DAILY_LOGIN_BONUS;
            $locked->last_daily_credit_at = now();
            $locked->save();

            return (int) $locked->credits;
        });
    }

    /**
     * Activate a paid plan via introductory promo (no payment yet).
     */
    public function activatePromoPlan(User $user, string $planKey): User
    {
        $plan = $this->plan($planKey);
        if ($plan === null || ! $plan->is_active || $plan->isFree()) {
            throw new RuntimeException(__('messages.credits_package_invalid'));
        }

        return DB::transaction(function () use ($user, $plan, $planKey) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $locked->plan = $planKey;
            $locked->plan_started_at = now();
            $locked->credits = (int) $locked->credits + (int) $plan->credits;
            $locked->save();

            return $locked->fresh();
        });
    }

    public function grant(User $user, int $amount): int
    {
        if ($amount <= 0) {
            return (int) $user->credits;
        }

        return (int) DB::transaction(function () use ($user, $amount) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->credits = (int) $locked->credits + $amount;
            $locked->save();

            return (int) $locked->credits;
        });
    }

    public function spend(User $user, int $cost): int
    {
        if ($cost <= 0) {
            return (int) $user->credits;
        }

        return DB::transaction(function () use ($user, $cost) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->credits < $cost) {
                throw new RuntimeException(__('messages.credits_empty'));
            }

            $locked->credits = (int) $locked->credits - $cost;
            $locked->save();

            return (int) $locked->credits;
        });
    }

    public function spendForQuestion(User $user): int
    {
        return $this->spend($user, self::QUESTION_COST);
    }

    public function hasQuestionCredit(User $user): bool
    {
        return (int) $user->credits >= self::QUESTION_COST;
    }

    public function canExportPdf(User $user): bool
    {
        return app(FeatureAccessService::class)->can($user, FeatureCatalog::PDF);
    }

    public function canViewHistory(User $user): bool
    {
        return app(FeatureAccessService::class)->can($user, FeatureCatalog::HISTORY);
    }

    public function hasAstroMottoAccess(User $user): bool
    {
        return app(FeatureAccessService::class)->can($user, FeatureCatalog::ASTROMOTTO);
    }

    private function refillAdminMonthlyCredits(User $locked): int
    {
        $monthStart = now()->startOfMonth();
        if ($locked->last_monthly_credit_at && $locked->last_monthly_credit_at->gte($monthStart)) {
            return (int) $locked->credits;
        }

        $locked->credits = max((int) $locked->credits, self::ADMIN_MONTHLY_CREDITS);
        $locked->last_monthly_credit_at = now();
        $locked->save();

        return (int) $locked->credits;
    }

    /**
     * @param  list<array{key: string}>  $items
     * @return array<string, mixed>|null
     */
    private function findByKey(array $items, string $key): ?array
    {
        foreach ($items as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }
}

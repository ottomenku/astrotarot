<?php

namespace App\Services;

use App\Models\User;
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
     * @return list<array{
     *   key: string,
     *   price_huf: int|null,
     *   monthly_credits: int,
     *   label_hu: string,
     *   label_en: string,
     *   features_hu: list<string>,
     *   features_en: list<string>
     * }>
     */
    public function plans(): array
    {
        return [
            [
                'key' => self::PLAN_FREE,
                'price_huf' => null,
                'monthly_credits' => 0,
                'label_hu' => 'Free',
                'label_en' => 'Free',
                'features_hu' => [
                    'Regisztrációkor 10 kredit',
                    'Minden nap +1 kredit bejelentkezéskor',
                    'Alap olvasatok',
                ],
                'features_en' => [
                    '10 credits on signup',
                    '+1 credit each day on login',
                    'Basic readings',
                ],
            ],
            [
                'key' => self::PLAN_PREMIUM,
                'price_huf' => 1600,
                'monthly_credits' => 50,
                'label_hu' => 'Premium',
                'label_en' => 'Premium',
                'features_hu' => [
                    'Havi 50 kredit',
                    'PDF mentés',
                    'Kérdések naplózása és visszanézése',
                ],
                'features_en' => [
                    '50 credits / month',
                    'PDF export',
                    'Question log & history',
                ],
            ],
            [
                'key' => self::PLAN_EXPERT,
                'price_huf' => 2500,
                'monthly_credits' => 100,
                'label_hu' => 'Expert',
                'label_en' => 'Expert',
                'features_hu' => [
                    'Havi 100 kredit',
                    'Minden Premium előny',
                    'Prémium hozzáférés az AstroMotto asztrológiai oldalhoz',
                ],
                'features_en' => [
                    '100 credits / month',
                    'All Premium benefits',
                    'Premium access to the AstroMotto astrology site',
                ],
            ],
        ];
    }

    public function plan(string $key): ?array
    {
        return $this->findByKey($this->plans(), $key);
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
            $plan = $locked->plan ?: self::PLAN_FREE;

            if ($plan !== self::PLAN_FREE) {
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
        if ($plan === null || $planKey === self::PLAN_FREE) {
            throw new RuntimeException(__('messages.credits_package_invalid'));
        }

        return DB::transaction(function () use ($user, $plan, $planKey) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $locked->plan = $planKey;
            $locked->plan_started_at = now();
            $locked->credits = (int) $locked->credits + (int) $plan['monthly_credits'];
            $locked->save();

            return $locked->fresh();
        });
    }

    public function spendForQuestion(User $user): int
    {
        return DB::transaction(function () use ($user) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->credits < self::QUESTION_COST) {
                throw new RuntimeException(__('messages.credits_empty'));
            }

            $locked->credits = (int) $locked->credits - self::QUESTION_COST;
            $locked->save();

            return (int) $locked->credits;
        });
    }

    public function hasQuestionCredit(User $user): bool
    {
        return (int) $user->credits >= self::QUESTION_COST;
    }

    public function canExportPdf(User $user): bool
    {
        return in_array($user->plan ?: self::PLAN_FREE, [self::PLAN_PREMIUM, self::PLAN_EXPERT], true);
    }

    public function canViewHistory(User $user): bool
    {
        return $this->canExportPdf($user);
    }

    public function hasAstroMottoAccess(User $user): bool
    {
        return ($user->plan ?: self::PLAN_FREE) === self::PLAN_EXPERT;
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

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditService
{
    public const SIGNUP_BONUS = 10;

    public const QUESTION_COST = 1;

    /**
     * @return list<array{key: string, credits: int, label_hu: string, label_en: string}>
     */
    public function packages(): array
    {
        return [
            [
                'key' => 'pack_10',
                'credits' => 10,
                'label_hu' => '10 kredit',
                'label_en' => '10 credits',
            ],
            [
                'key' => 'pack_30',
                'credits' => 30,
                'label_hu' => '30 kredit',
                'label_en' => '30 credits',
            ],
            [
                'key' => 'pack_100',
                'credits' => 100,
                'label_hu' => '100 kredit',
                'label_en' => '100 credits',
            ],
        ];
    }

    public function package(string $key): ?array
    {
        foreach ($this->packages() as $package) {
            if ($package['key'] === $key) {
                return $package;
            }
        }

        return null;
    }

    public function grantSignupBonus(User $user): void
    {
        $user->forceFill([
            'credits' => (int) $user->credits + self::SIGNUP_BONUS,
        ])->save();
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
}

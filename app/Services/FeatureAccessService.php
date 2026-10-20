<?php

namespace App\Services;

use App\Models\FeatureAccess;
use App\Models\Package;
use App\Models\User;
use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use App\Support\PackageIcons;
use Illuminate\Support\Collection;

class FeatureAccessService
{
    /** @var Collection<string, FeatureAccess>|null */
    private ?Collection $cache = null;

    public function __construct(private readonly AppSettings $settings) {}

    public function refresh(): void
    {
        $this->cache = null;
    }

    public function syncCatalog(): void
    {
        $existing = FeatureAccess::query()->pluck('key')->all();
        $missing = array_diff(FeatureCatalog::keys(), $existing);
        foreach ($missing as $key) {
            FeatureAccess::query()->create([
                'key' => $key,
                'label_hu' => PackageBody::perkLabel($key, 'hu'),
                'label_en' => PackageBody::perkLabel($key, 'en'),
                'credit_cost' => $key === FeatureCatalog::READING ? 1 : 0,
                'logic' => FeatureCatalog::LOGIC_AND,
            ]);
        }
        if ($missing !== []) {
            $this->refresh();
        }
    }

    /**
     * @return Collection<string, FeatureAccess>
     */
    public function all(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->syncCatalog();

        return $this->cache = FeatureAccess::query()
            ->with('packages')
            ->get()
            ->keyBy('key');
    }

    public function rule(string $key): ?FeatureAccess
    {
        return $this->all()->get($key);
    }

    public function can(?User $user, string $key): bool
    {
        return $this->decide($user, $key)['allowed'];
    }

    /**
     * @return array{allowed: bool, cost: int, will_charge: bool, status: int, message: string}
     */
    public function decide(?User $user, string $key): array
    {
        $denied = [
            'allowed' => false,
            'cost' => 0,
            'will_charge' => false,
            'status' => 403,
            'message' => __('messages.feature_locked'),
        ];

        if ($user === null) {
            if (in_array($key, [FeatureCatalog::READING, FeatureCatalog::CLARIFY, FeatureCatalog::EXPAND], true)) {
                return [
                    'allowed' => true,
                    'cost' => 0,
                    'will_charge' => false,
                    'status' => 200,
                    'message' => '',
                ];
            }

            $denied['message'] = __('messages.credits_auth_required');
            $denied['status'] = 401;

            return $denied;
        }

        if (! $this->settings->paidFeaturesEnabled()) {
            return [
                'allowed' => true,
                'cost' => 0,
                'will_charge' => false,
                'status' => 200,
                'message' => '',
            ];
        }

        if ($user->isAdmin()) {
            return [
                'allowed' => true,
                'cost' => 0,
                'will_charge' => false,
                'status' => 200,
                'message' => '',
            ];
        }

        $rule = $this->rule($key);
        if ($rule === null) {
            return $denied;
        }

        $cost = max(0, (int) $rule->credit_cost);
        $userPackage = $user->planKey();
        $hasPackage = $rule->packages->isEmpty()
            || $rule->packages->contains(fn ($package) => $package->slug === $userPackage);
        $hasCredits = $cost === 0 || (int) $user->credits >= $cost;
        $logic = $rule->logic === FeatureCatalog::LOGIC_OR
            ? FeatureCatalog::LOGIC_OR
            : FeatureCatalog::LOGIC_AND;

        $allowed = $logic === FeatureCatalog::LOGIC_OR
            ? ($hasPackage || ($cost > 0 && $hasCredits))
            : ($hasPackage && $hasCredits);

        $willCharge = $allowed
            && FeatureCatalog::charges($key)
            && $cost > 0
            && ! ($logic === FeatureCatalog::LOGIC_OR && $hasPackage);

        if ($allowed) {
            return [
                'allowed' => true,
                'cost' => $willCharge ? $cost : 0,
                'will_charge' => $willCharge,
                'status' => 200,
                'message' => '',
            ];
        }

        $needPackage = ! $hasPackage && ($logic === FeatureCatalog::LOGIC_AND || $cost === 0);
        $status = (! $hasCredits && $cost > 0 && ! $needPackage) ? 402 : 403;

        return [
            'allowed' => false,
            'cost' => $cost,
            'will_charge' => false,
            'status' => $status,
            'message' => $status === 402
                ? __('messages.credits_empty')
                : __('messages.feature_locked'),
        ];
    }

    /**
     * @return array<string, array{allowed: bool, cost: int, will_charge: bool, message: string}>
     */
    public function snapshot(?User $user): array
    {
        $out = [];
        foreach (FeatureCatalog::keys() as $key) {
            $decision = $this->decide($user, $key);
            $marks = $this->marks($key);
            $out[$key] = [
                'allowed' => $decision['allowed'],
                'cost' => $decision['cost'],
                'will_charge' => $decision['will_charge'],
                'message' => $decision['message'],
                'credit_cost' => $marks['credit_cost'],
                'packages' => $marks['packages'],
            ];
        }

        return $out;
    }

    /**
     * @return array{packages: list<array{slug: string, icon: string, name: string}>, credit_cost: int}
     */
    public function marks(string $key): array
    {
        if (! $this->settings->paidFeaturesEnabled()) {
            return [
                'packages' => [],
                'credit_cost' => 0,
            ];
        }

        $rule = $this->rule($key);
        $packages = [];
        if ($rule !== null && $rule->packages->isNotEmpty()) {
            $lowest = $rule->packages->sort(function (Package $left, Package $right): int {
                return [
                    (int) $left->price_huf,
                    (float) $left->price_usd,
                    (int) $left->sort_order,
                    (int) $left->id,
                ] <=> [
                    (int) $right->price_huf,
                    (float) $right->price_usd,
                    (int) $right->sort_order,
                    (int) $right->id,
                ];
            })->first();

            $icon = $lowest->iconKey();
            if ($icon !== PackageIcons::NONE) {
                $packages[] = [
                    'slug' => $lowest->slug,
                    'icon' => $icon,
                    'name' => $lowest->name(),
                ];
            }
        }

        return [
            'packages' => $packages,
            'credit_cost' => max(0, (int) ($rule?->credit_cost ?? 0)),
        ];
    }

    /**
     * @return array<string, array{hu: string, en: string}>
     */
    public function labelMap(): array
    {
        $map = [];
        foreach ($this->all() as $rule) {
            $map[$rule->key] = [
                'hu' => $rule->label('hu'),
                'en' => $rule->label('en'),
            ];
        }

        return $map;
    }

    public function abortUnlessCan(?User $user, string $key): void
    {
        $decision = $this->decide($user, $key);
        if ($decision['allowed']) {
            return;
        }

        abort($decision['status'], $decision['message']);
    }
}

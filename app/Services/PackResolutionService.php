<?php

namespace App\Services;

use App\Models\CardPack;
use App\Models\Game;
use App\Models\GameRule;
use App\Models\User;
use App\Models\UserPackPreference;
use App\Models\UserSpreadPreference;
use App\Support\CardPackCatalog;
use App\Support\FeatureCatalog;
use Illuminate\Support\Collection;

class PackResolutionService
{
    public function __construct(private readonly FeatureAccessService $features) {}

    public function figurePack(?User $user, string $packType, ?CardPack $gameDefault = null): ?CardPack
    {
        if ($user !== null && $this->features->can($user, FeatureCatalog::PACKS_CHOOSE)) {
            $chosen = $this->chosenFigure($user, $packType);
            if ($chosen !== null) {
                return $chosen;
            }
        }

        if ($gameDefault !== null && $gameDefault->is_active && $gameDefault->type === $packType) {
            return $gameDefault;
        }

        return CardPack::defaultFor($packType);
    }

    /**
     * @return array{card_back: ?string}
     */
    public function skin(?User $user, CardPack $figure): array
    {
        $figure->loadMissing('defaultCardBack');
        $back = $figure->defaultCardBack;

        if ($user !== null && $this->features->can($user, FeatureCatalog::PACKS_BACKS)) {
            $chosen = $this->chosenCardBack($user);
            if ($chosen !== null) {
                $back = $chosen;
            }
        }

        return [
            'card_back' => $back?->fileUrl() ?? $this->assetUrl(CardPackCatalog::cardBackRelative($figure->slug)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveRule(?User $user, GameRule $rule): array
    {
        $type = $rule->pack_type ?: CardPack::TYPE_TAROT;
        $rule->loadMissing('defaultPack');
        $pack = $this->figurePack($user, $type, $rule->defaultPack);
        $slug = $pack?->slug ?? $rule->pack_slug;
        $skin = $pack !== null
            ? $this->skin($user, $pack)
            : [
                'card_back' => $this->assetUrl(CardPackCatalog::cardBackRelative($slug)),
            ];

        return [
            'pack' => $pack,
            'slug' => $slug,
            'type' => $type,
            'skin' => $skin,
        ];
    }

    public function allowedDeck(?User $user, Game $game, int $index, string $deck): string
    {
        $game->loadMissing(['rules.defaultPack']);
        $rule = $game->rules->values()->get($index);
        if ($rule === null) {
            return CardPackCatalog::canonicalize($deck);
        }

        $resolved = $this->resolveRule($user, $rule);
        $sessionSlug = $this->spreadPackSlug($user, (string) $resolved['type']);
        if ($sessionSlug !== '') {
            $sessionPack = CardPack::query()->active()->figure()->where('slug', $sessionSlug)->first();
            if ($sessionPack !== null && $sessionPack->type === $resolved['type']) {
                return $sessionPack->slug;
            }
        }

        $slug = CardPackCatalog::canonicalize($deck);
        if (! $this->features->can($user, FeatureCatalog::PACKS_CHOOSE)) {
            return $resolved['slug'];
        }

        $pack = CardPack::query()->active()->figure()->where('slug', $slug)->first();
        if ($pack === null || $pack->type !== $resolved['type']) {
            return $resolved['slug'];
        }

        return $pack->slug;
    }

    /**
     * @return Collection<int, CardPack>
     */
    public function figureOptions(string $packType): Collection
    {
        return CardPack::query()->active()->ofType($packType)->ordered()->get();
    }

    /**
     * @return Collection<int, CardPack>
     */
    public function backOptions(): Collection
    {
        return CardPack::query()->active()->ofType(CardPack::TYPE_CARD_BACK)->ordered()->get();
    }

    public function chosenCardBack(User $user): ?CardPack
    {
        $pref = UserPackPreference::query()
            ->where('user_id', $user->id)
            ->where('role', UserPackPreference::ROLE_CARD_BACK)
            ->orderByRaw('case when figure_pack_id is null then 0 else 1 end')
            ->latest('id')
            ->with('cardPack')
            ->first();

        $pack = $pref?->cardPack;
        if ($pack === null || ! $pack->is_active || $pack->type !== CardPack::TYPE_CARD_BACK) {
            return null;
        }

        return $pack;
    }

    /** @var array<int, array<string, string>|null> */
    private array $spreadPackCache = [];

    private function spreadPackSlug(?User $user, string $type): string
    {
        if ($user !== null) {
            if (! array_key_exists($user->id, $this->spreadPackCache)) {
                $stored = UserSpreadPreference::query()->where('user_id', $user->id)->first();
                $packs = $stored !== null && is_array($stored->packs) ? $stored->packs : null;
                $this->spreadPackCache[$user->id] = $packs;
            }
            $packs = $this->spreadPackCache[$user->id];
            if (is_array($packs)) {
                return trim((string) ($packs[$type] ?? ''));
            }
        }

        $sessionPacks = session('spread.packs', []);

        return is_array($sessionPacks) ? trim((string) ($sessionPacks[$type] ?? '')) : '';
    }

    private function chosenFigure(User $user, string $packType): ?CardPack
    {
        $pref = UserPackPreference::query()
            ->where('user_id', $user->id)
            ->where('role', UserPackPreference::ROLE_FIGURE)
            ->where('pack_type', $packType)
            ->with('cardPack')
            ->first();

        $pack = $pref?->cardPack;
        if ($pack === null || ! $pack->is_active || $pack->type !== $packType) {
            return null;
        }

        return $pack;
    }

    private function assetUrl(?string $relative): ?string
    {
        if ($relative === null || $relative === '' || ! is_file(public_path($relative))) {
            return null;
        }

        return asset($relative);
    }
}

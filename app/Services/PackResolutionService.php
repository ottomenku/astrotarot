<?php

namespace App\Services;

use App\Models\CardPack;
use App\Models\Game;
use App\Models\GameRule;
use App\Models\User;
use App\Models\UserPackPreference;
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
     * @return array{card_back: ?string, background_light: ?string, background_shadow: ?string, has_backgrounds: bool}
     */
    public function skin(?User $user, CardPack $figure): array
    {
        $figure->loadMissing(['defaultCardBack', 'defaultBackgroundLight', 'defaultBackgroundShadow']);
        $back = $figure->defaultCardBack;
        $light = $figure->defaultBackgroundLight;
        $shadow = $figure->defaultBackgroundShadow;

        if ($user !== null && $this->features->can($user, FeatureCatalog::PACKS_BACKS)) {
            $chosen = $this->chosenSkin($user, $figure, UserPackPreference::ROLE_CARD_BACK);
            if ($chosen !== null) {
                $back = $chosen;
            }
        }

        if ($user !== null && $this->features->can($user, FeatureCatalog::PACKS_BACKGROUNDS)) {
            $chosenLight = $this->chosenSkin($user, $figure, UserPackPreference::ROLE_BACKGROUND_LIGHT);
            $chosenShadow = $this->chosenSkin($user, $figure, UserPackPreference::ROLE_BACKGROUND_SHADOW);
            if ($chosenLight !== null) {
                $light = $chosenLight;
            }
            if ($chosenShadow !== null) {
                $shadow = $chosenShadow;
            }
        }

        $backUrl = $back?->fileUrl() ?? $this->assetUrl(CardPackCatalog::cardBackRelative($figure->slug));
        $lightUrl = $light?->fileUrl() ?? $this->assetUrl(CardPackCatalog::backgroundRelative($figure->slug, 'light'));
        $shadowUrl = $shadow?->fileUrl() ?? $this->assetUrl(CardPackCatalog::backgroundRelative($figure->slug, 'shadow'));

        return [
            'card_back' => $backUrl,
            'background_light' => $lightUrl,
            'background_shadow' => $shadowUrl,
            'has_backgrounds' => $lightUrl !== null || $shadowUrl !== null,
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
                'background_light' => $this->assetUrl(CardPackCatalog::backgroundRelative($slug, 'light')),
                'background_shadow' => $this->assetUrl(CardPackCatalog::backgroundRelative($slug, 'shadow')),
                'has_backgrounds' => CardPackCatalog::hasBackgrounds($slug),
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

    /**
     * @return Collection<int, CardPack>
     */
    public function backgroundOptions(string $polarity): Collection
    {
        return CardPack::query()
            ->active()
            ->ofType(CardPack::TYPE_BACKGROUND)
            ->where('polarity', $polarity)
            ->ordered()
            ->get();
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

    private function chosenSkin(User $user, CardPack $figure, string $role): ?CardPack
    {
        $pref = UserPackPreference::query()
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->where('figure_pack_id', $figure->id)
            ->with('cardPack')
            ->first();

        $pack = $pref?->cardPack;
        if ($pack === null || ! $pack->is_active) {
            return null;
        }

        if ($role === UserPackPreference::ROLE_CARD_BACK && $pack->type !== CardPack::TYPE_CARD_BACK) {
            return null;
        }
        if ($role === UserPackPreference::ROLE_BACKGROUND_LIGHT
            && ($pack->type !== CardPack::TYPE_BACKGROUND || $pack->polarity !== CardPack::POLARITY_LIGHT)) {
            return null;
        }
        if ($role === UserPackPreference::ROLE_BACKGROUND_SHADOW
            && ($pack->type !== CardPack::TYPE_BACKGROUND || $pack->polarity !== CardPack::POLARITY_SHADOW)) {
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

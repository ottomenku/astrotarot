<?php

namespace App\Services;

use App\Models\CardPack;
use App\Models\Game;
use App\Support\CardPackCatalog;
use App\Support\FeatureCatalog;

class GameConfigService
{
    public function __construct(
        private readonly PackResolutionService $packs,
        private readonly FeatureAccessService $features,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function homepage(?string $locale = null): array
    {
        $game = Game::query()
            ->active()
            ->where('is_default', true)
            ->with(['rules' => fn ($query) => $query->ordered()->with('defaultPack')])
            ->first()
            ?? Game::query()
                ->active()
                ->with(['rules' => fn ($query) => $query->ordered()->with('defaultPack')])
                ->ordered()
                ->first();

        if ($game === null) {
            return $this->empty($locale);
        }

        return $this->toArray($game, $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Game $game, ?string $locale = null): array
    {
        $locale = $locale === 'en' ? 'en' : ($locale ?? app()->getLocale());
        $locale = $locale === 'en' ? 'en' : 'hu';
        $user = auth()->user();

        $slots = [];
        $packs = [];
        foreach ($game->rules as $rule) {
            $resolved = $this->packs->resolveRule($user, $rule);
            $slug = $resolved['slug'];
            $skin = $resolved['skin'];
            $slots[] = [
                'pack_slug' => $slug,
                'pack_type' => $resolved['type'],
                'title' => $rule->title($locale),
                'title_hu' => $rule->title_hu,
                'title_en' => $rule->title_en,
                'info_mode' => $rule->info_mode,
                'row' => (int) $rule->row,
                'place' => (int) $rule->place,
                'full_bleed' => true,
            ];
            $packs[$slug] = [
                'slug' => $slug,
                'type' => $resolved['type'],
                'polar_figures' => CardPackCatalog::hasPolarFigures($slug),
                'card_back' => $skin['card_back'],
            ];
        }

        usort($slots, function (array $a, array $b): int {
            return [$a['row'], $a['place']] <=> [$b['row'], $b['place']];
        });

        $options = [];
        foreach (CardPack::FIGURE_TYPES as $type) {
            $options[$type] = $this->packs->figureOptions($type)->map(fn (CardPack $pack) => [
                'id' => $pack->id,
                'slug' => $pack->slug,
                'name' => $pack->name($locale),
                'preview' => $pack->previewUrl(),
            ])->values()->all();
        }

        return [
            'id' => $game->id,
            'slug' => $game->slug,
            'name' => $game->name($locale),
            'description' => $game->description($locale),
            'description_hu' => (string) $game->description_hu,
            'description_en' => (string) $game->description_en,
            'icon' => $game->iconUrl(),
            'has_question' => (bool) $game->has_question,
            'slots' => $slots,
            'pack_base' => asset(CardPackCatalog::publicPrefix()),
            'packs' => $packs,
            'pack_options' => $options,
            'can_choose_packs' => $user !== null && $this->features->can($user, FeatureCatalog::PACKS_CHOOSE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(?string $locale): array
    {
        return [
            'id' => null,
            'slug' => null,
            'name' => '',
            'description' => '',
            'description_hu' => '',
            'description_en' => '',
            'icon' => null,
            'has_question' => true,
            'slots' => [],
            'pack_base' => asset(CardPackCatalog::publicPrefix()),
            'packs' => [],
            'pack_options' => [],
            'can_choose_packs' => false,
        ];
    }
}

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
    /**
     * @param  array{game?: int|null, packs?: array<string, string>, back?: int|null}  $selection
     * @return array<string, mixed>
     */
    public function homepage(?string $locale = null, array $selection = []): array
    {
        $withRules = ['rules' => fn ($query) => $query->ordered()->with('defaultPack')];
        $game = null;
        $gameId = (int) ($selection['game'] ?? 0);
        if ($gameId > 0) {
            $game = Game::query()->active()->with($withRules)->find($gameId);
        }

        $game ??= Game::query()
            ->active()
            ->where('is_default', true)
            ->with($withRules)
            ->first()
            ?? Game::query()
                ->active()
                ->with($withRules)
                ->ordered()
                ->first();

        if ($game === null) {
            return $this->empty($locale);
        }

        return $this->toArray($game, $locale, $selection);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{game?: int|null, packs?: array<string, string>, back?: int|null}  $selection
     * @return array<string, mixed>
     */
    public function toArray(Game $game, ?string $locale = null, array $selection = []): array
    {
        $locale = $locale === 'en' ? 'en' : ($locale ?? app()->getLocale());
        $locale = $locale === 'en' ? 'en' : 'hu';
        $user = auth()->user();
        $chosenPacks = is_array($selection['packs'] ?? null) ? $selection['packs'] : [];

        $slots = [];
        $packs = [];
        foreach ($game->rules as $rule) {
            $resolved = $this->packs->resolveRule($user, $rule);
            $slug = $resolved['slug'];
            $skin = $resolved['skin'];
            $overrideSlug = trim((string) ($chosenPacks[$resolved['type']] ?? ''));
            if ($overrideSlug !== '') {
                $override = CardPack::query()->active()->ofType($resolved['type'])->where('slug', $overrideSlug)->first();
                if ($override !== null) {
                    $slug = $override->slug;
                    $skin = $this->packs->skin($user, $override);
                }
            }
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

        $backId = (int) ($selection['back'] ?? 0);
        $backUrl = null;
        if ($backId > 0) {
            $back = CardPack::query()->active()->ofType(CardPack::TYPE_CARD_BACK)->find($backId);
            $backUrl = $back?->fileUrl();
        }
        if (is_string($backUrl) && $backUrl !== '') {
            foreach ($packs as $slug => $meta) {
                $packs[$slug]['card_back'] = $backUrl;
            }
        }

        $options = [];
        foreach (CardPack::FIGURE_TYPES as $type) {
            $options[$type] = $this->packs->figureOptions($type)->map(fn (CardPack $pack) => [
                'id' => $pack->id,
                'slug' => $pack->slug,
                'name' => $pack->name($locale),
                'name_hu' => (string) $pack->name_hu,
                'name_en' => (string) $pack->name_en,
                'preview' => $pack->previewUrl(),
            ])->values()->all();
        }

        return [
            'id' => $game->id,
            'slug' => $game->slug,
            'name' => $game->name($locale),
            'name_hu' => (string) $game->name_hu,
            'name_en' => (string) $game->name_en,
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
            'choices' => $this->choices($locale, $game, $slots, $backId),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     * @return array<string, mixed>
     */
    private function choices(string $locale, Game $current, array $slots, int $backId): array
    {
        $selectedPacks = [];
        foreach ($slots as $slot) {
            $type = (string) ($slot['pack_type'] ?? '');
            if ($type !== '' && ! isset($selectedPacks[$type])) {
                $selectedPacks[$type] = (string) ($slot['pack_slug'] ?? '');
            }
        }

        $games = Game::query()
            ->active()
            ->ordered()
            ->with(['rules'])
            ->get()
            ->map(function (Game $game) {
                $types = $game->rules
                    ->map(fn ($rule) => $rule->pack_type ?: CardPackCatalog::typeForSlug((string) $rule->pack_slug))
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $game->id,
                    'name_hu' => (string) $game->name_hu,
                    'name_en' => (string) $game->name_en,
                    'slot_count' => $game->rules->count(),
                    'types' => $types,
                ];
            })
            ->all();

        $backs = $this->packs->backOptions()->map(fn (CardPack $back) => [
            'id' => $back->id,
            'name_hu' => (string) $back->name_hu,
            'name_en' => (string) $back->name_en,
            'preview' => $back->fileUrl(),
        ])->values()->all();

        return [
            'games' => $games,
            'backs' => $backs,
            'selected_game' => $current->id,
            'selected_packs' => $selectedPacks,
            'selected_back' => $backId > 0 ? $backId : null,
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
            'name_hu' => '',
            'name_en' => '',
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
            'choices' => [
                'games' => [],
                'backs' => [],
                'selected_game' => null,
                'selected_packs' => [],
                'selected_back' => null,
            ],
        ];
    }
}

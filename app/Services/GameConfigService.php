<?php

namespace App\Services;

use App\Models\Game;
use App\Support\CardPackCatalog;

class GameConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function homepage(?string $locale = null): array
    {
        $game = Game::query()
            ->active()
            ->where('is_default', true)
            ->with(['rules' => fn ($query) => $query->ordered()])
            ->first()
            ?? Game::query()->active()->with(['rules' => fn ($query) => $query->ordered()])->ordered()->first();

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

        $slots = [];
        $packSlugs = [];
        foreach ($game->rules as $rule) {
            $packSlugs[] = $rule->pack_slug;
            $slots[] = [
                'pack_slug' => $rule->pack_slug,
                'title' => $rule->title($locale),
                'title_hu' => $rule->title_hu,
                'title_en' => $rule->title_en,
                'info_mode' => $rule->info_mode,
                'row' => (int) $rule->row,
                'place' => (int) $rule->place,
                'full_bleed' => ! CardPackCatalog::hasBackgrounds($rule->pack_slug),
            ];
        }

        usort($slots, function (array $a, array $b): int {
            return [$a['row'], $a['place']] <=> [$b['row'], $b['place']];
        });

        $packs = [];
        foreach (array_unique($packSlugs) as $slug) {
            $packs[$slug] = [
                'slug' => $slug,
                'has_backgrounds' => CardPackCatalog::hasBackgrounds($slug),
            ];
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
        ];
    }
}

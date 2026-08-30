<?php

namespace App\Support;

final class PackageBody
{
    public const PERKS_CLASS = 'package-perks';

    /**
     * @param  list<string>  $perkKeys
     * @param  array<string, array{hu?: string, en?: string}>  $labels
     * @return list<string>
     */
    public static function items(string $locale, int $credits, string $period, array $perkKeys, array $labels = []): array
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $items = [];

        if ($credits > 0) {
            $items[] = $period === 'yearly'
                ? ($locale === 'en'
                    ? $credits.' credits / year'
                    : $credits.' kredit / év')
                : ($locale === 'en'
                    ? $credits.' credits / month'
                    : $credits.' kredit / hó');
        }

        foreach (FeatureCatalog::packagePerks() as $key) {
            if (! in_array($key, $perkKeys, true)) {
                continue;
            }
            $items[] = self::perkLabel($key, $locale, $labels);
        }

        return $items;
    }

    /**
     * @param  array<string, array{hu?: string, en?: string}>  $labels
     */
    public static function perkLabel(string $key, string $locale, array $labels = []): string
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $custom = trim((string) ($labels[$key][$locale] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $defaults = [
            FeatureCatalog::READING => ['hu' => 'Új olvasat', 'en' => 'New reading'],
            FeatureCatalog::CLARIFY => ['hu' => 'Pontosítás', 'en' => 'Clarification'],
            FeatureCatalog::EXPAND => ['hu' => 'Bővebb kifejtés', 'en' => 'Longer explanation'],
            FeatureCatalog::PDF => ['hu' => 'PDF mentés', 'en' => 'PDF download'],
            FeatureCatalog::EMAIL => ['hu' => 'Olvasat e-mailben', 'en' => 'Email the reading'],
            FeatureCatalog::SHARE => ['hu' => 'Facebook megosztás', 'en' => 'Facebook share'],
            FeatureCatalog::HISTORY => ['hu' => 'Kérdésnapló', 'en' => 'Question log'],
            FeatureCatalog::ASTROMOTTO => ['hu' => 'AstroMotto hozzáférés', 'en' => 'AstroMotto access'],
            FeatureCatalog::CREDITS_PAGE => ['hu' => 'Kreditek / Csomagok', 'en' => 'Credits / Packages'],
            FeatureCatalog::CREDITS_REQUEST => ['hu' => 'Kreditigénylés', 'en' => 'Credit request'],
            FeatureCatalog::CREDITS_PROMO => ['hu' => 'Csomag promóció', 'en' => 'Package promo'],
            FeatureCatalog::ACCOUNT => ['hu' => 'Beállítások', 'en' => 'Settings'],
        ];

        return $defaults[$key][$locale] ?? $key;
    }

    /**
     * @param  list<string>  $items
     */
    public static function merge(string $html, array $items): string
    {
        $html = self::stripPerks($html);
        $list = self::renderList($items);
        $html = trim($html);

        if ($list === '') {
            return HtmlSanitizer::planBody($html);
        }

        return HtmlSanitizer::planBody($html === '' ? $list : $html.$list);
    }

    public static function stripPerks(string $html): string
    {
        $html = preg_replace(
            '#<ul[^>]*class="[^"]*'.preg_quote(self::PERKS_CLASS, '#').'[^"]*"[^>]*>.*?</ul>#is',
            '',
            $html
        ) ?? $html;

        return trim($html);
    }

    /**
     * @param  list<string>  $items
     */
    public static function renderList(array $items): string
    {
        $items = array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            $items
        )));
        if ($items === []) {
            return '';
        }

        $lis = '';
        foreach ($items as $item) {
            $lis .= '<li>'.htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }

        return '<ul class="'.self::PERKS_CLASS.'">'.$lis.'</ul>';
    }
}

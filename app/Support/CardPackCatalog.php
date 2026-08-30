<?php

namespace App\Support;

class CardPackCatalog
{
    /** @var array<string, string> */
    public const ALIASES = [
        'oracle' => 'egi-orakulum',
        'egi-orakulum' => 'egi-orakulum',
        'tarot' => 'tarot',
        'tarot-major' => 'tarot',
        'cigany' => 'cigany-kartya',
        'cigany-kartya' => 'cigany-kartya',
    ];

    public static function root(): string
    {
        return public_path('card-pack');
    }

    public static function publicPrefix(): string
    {
        return 'card-pack';
    }

    public static function canonicalize(string $deck): string
    {
        $key = strtolower(trim($deck));

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $root = self::root();
        if (! is_dir($root)) {
            return [];
        }

        $slugs = [];
        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $dir = $root.DIRECTORY_SEPARATOR.$name;
            if (! is_dir($dir)) {
                continue;
            }
            if (! is_dir($dir.DIRECTORY_SEPARATOR.'card-back')
                || ! is_dir($dir.DIRECTORY_SEPARATOR.'backgrounds')
                || ! is_dir($dir.DIRECTORY_SEPARATOR.'figures')) {
                continue;
            }
            $slugs[] = $name;
        }

        sort($slugs);

        return $slugs;
    }

    public static function packAbsolute(string $slug): string
    {
        return self::root().DIRECTORY_SEPARATOR.self::canonicalize($slug);
    }

    public static function hasBackgrounds(string $slug): bool
    {
        $slug = self::canonicalize($slug);
        foreach (['shared-light.png', 'shared-shadow.png'] as $file) {
            if (is_file(self::packAbsolute($slug).DIRECTORY_SEPARATOR.'backgrounds'.DIRECTORY_SEPARATOR.$file)) {
                return true;
            }
        }

        return false;
    }

    public static function figureRelative(string $deck, string $figureId): ?string
    {
        $slug = self::canonicalize($deck);
        $figureId = trim($figureId);
        if ($slug === '' || $figureId === '') {
            return null;
        }

        $candidates = [];
        if ($slug === 'tarot') {
            if ($figureId === 'significator') {
                $candidates[] = self::publicPrefix().'/tarot/figures/significator.png';
            }
            if (preg_match('/^(wands|cups|swords|pentacles)-/', $figureId) === 1) {
                $candidates[] = self::publicPrefix()."/tarot/figures/minors/{$figureId}.png";
            }
        }
        $candidates[] = self::publicPrefix()."/{$slug}/figures/{$figureId}.png";

        foreach ($candidates as $file) {
            if (is_file(public_path($file))) {
                return $file;
            }
        }

        return null;
    }

    public static function backgroundRelative(string $deck, string $variant): ?string
    {
        $slug = self::canonicalize($deck);
        $tone = in_array($variant, ['shadow', 'negative', 'negatív'], true) ? 'shadow' : 'light';
        $file = self::publicPrefix()."/{$slug}/backgrounds/shared-{$tone}.png";

        return is_file(public_path($file)) ? $file : null;
    }

    public static function cardBackRelative(string $slug): string
    {
        $slug = self::canonicalize($slug);
        foreach (['card-back/card-back.png', 'card-back/card-back.webp'] as $relative) {
            $file = self::publicPrefix()."/{$slug}/{$relative}";
            if (is_file(public_path($file))) {
                return $file;
            }
        }

        return self::publicPrefix()."/{$slug}/card-back/card-back.png";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function deckJson(string $slug): ?array
    {
        $slug = self::canonicalize($slug);
        $path = self::packAbsolute($slug).DIRECTORY_SEPARATOR.'deck.json';
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function figureBlurb(string $deck, string $figureId, string $locale): string
    {
        $deck = self::deckJson($deck);
        if ($deck === null) {
            return '';
        }

        foreach ($deck['figures'] ?? [] as $figure) {
            if (! is_array($figure) || (string) ($figure['id'] ?? '') !== $figureId) {
                continue;
            }
            $blurb = $figure['blurb'] ?? null;
            if (is_array($blurb)) {
                $text = (string) ($blurb[$locale] ?? $blurb['en'] ?? $blurb['hu'] ?? '');

                return trim($text);
            }

            return trim((string) $blurb);
        }

        return '';
    }

    public static function figureName(string $deck, string $figureId, string $locale): string
    {
        $deck = self::deckJson($deck);
        if ($deck === null) {
            return $figureId;
        }

        foreach ($deck['figures'] ?? [] as $figure) {
            if (! is_array($figure) || (string) ($figure['id'] ?? '') !== $figureId) {
                continue;
            }

            return (string) ($figure[$locale] ?? $figure['en'] ?? $figure['hu'] ?? $figureId);
        }

        return $figureId;
    }
}

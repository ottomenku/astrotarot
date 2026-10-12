<?php

namespace App\Support;

use App\Models\CardPack;
use Illuminate\Support\Facades\Schema;

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

    public static function typeForSlug(string $slug): string
    {
        $slug = self::canonicalize($slug);
        $pack = self::figure($slug);
        if ($pack !== null) {
            return $pack->type;
        }

        return $slug === 'egi-orakulum' ? CardPack::TYPE_ORACLE : CardPack::TYPE_TAROT;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $fromDb = self::figureSlugsFromDb();
        if ($fromDb !== []) {
            return $fromDb;
        }

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
            if (! is_dir($dir.DIRECTORY_SEPARATOR.'figures')) {
                continue;
            }
            $slugs[] = $name;
        }

        sort($slugs);

        return $slugs;
    }

    public static function packAbsolute(string $slug): string
    {
        $pack = self::figure($slug);
        if ($pack !== null) {
            $folder = $pack->folderRelative();
            if ($folder !== null) {
                return public_path($folder);
            }
        }

        return self::root().DIRECTORY_SEPARATOR.self::canonicalize($slug);
    }

    public static function figure(string $slug): ?CardPack
    {
        if (! self::packsTableReady()) {
            return null;
        }

        return CardPack::query()->figure()->where('slug', self::canonicalize($slug))->first();
    }

    /**
     * @return list<string>
     */
    private static function figureSlugsFromDb(): array
    {
        if (! self::packsTableReady()) {
            return [];
        }

        return array_values(CardPack::query()->figure()->orderBy('slug')->pluck('slug')->all());
    }

    private static function packsTableReady(): bool
    {
        try {
            return Schema::hasTable('card_packs');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasPolarFigures(string $slug): bool
    {
        $deck = self::deckJson($slug);
        if (is_array($deck) && array_key_exists('polarFigures', $deck)) {
            return (bool) $deck['polarFigures'];
        }

        $id = trim((string) ($deck['figures'][0]['id'] ?? ''));
        if ($id === '') {
            return false;
        }

        return is_file(self::packAbsolute($slug).DIRECTORY_SEPARATOR.'figures'.DIRECTORY_SEPARATOR.$id.'-light.png');
    }

    public static function figureRelative(string $deck, string $figureId, ?string $variant = null): ?string
    {
        $slug = self::canonicalize($deck);
        $figureId = trim($figureId);
        if ($slug === '' || $figureId === '') {
            return null;
        }

        $folder = self::publicPrefix().'/'.$slug;
        $pack = self::figure($slug);
        if ($pack !== null) {
            $folder = $pack->folderRelative() ?? $folder;
        }

        $tone = null;
        if ($variant !== null && trim($variant) !== '') {
            $tone = in_array($variant, ['shadow', 'negative', 'negatív'], true)
                ? 'shadow'
                : 'light';
        }

        $candidates = [];
        if ($tone !== null) {
            $candidates[] = $folder."/figures/{$figureId}-{$tone}.png";
            $candidates[] = $folder."/figures/{$tone}/{$figureId}.png";
        } elseif (self::hasPolarFigures($slug)) {
            $candidates[] = $folder."/figures/{$figureId}-light.png";
        }
        if ($slug === 'tarot') {
            if ($figureId === 'significator') {
                $candidates[] = $folder.'/figures/significator.png';
            }
            if (preg_match('/^(wands|cups|swords|pentacles)-/', $figureId) === 1) {
                $candidates[] = $folder."/figures/minors/{$figureId}.png";
            }
        }
        $candidates[] = $folder."/figures/{$figureId}.png";
        if ($tone !== null) {
            $other = $tone === 'shadow' ? 'light' : 'shadow';
            $candidates[] = $folder."/figures/{$figureId}-{$other}.png";
        }

        foreach ($candidates as $file) {
            if (is_file(public_path($file))) {
                return $file;
            }
        }

        return null;
    }

    public static function cardBackRelative(string $slug): string
    {
        $slug = self::canonicalize($slug);
        $fromPack = self::figure($slug)?->defaultCardBack?->fileRelative();
        if ($fromPack !== null) {
            return $fromPack;
        }

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

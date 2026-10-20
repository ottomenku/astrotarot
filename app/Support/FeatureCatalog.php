<?php

namespace App\Support;

final class FeatureCatalog
{
    public const READING = 'reading.initial';

    public const CLARIFY = 'reading.clarify';

    public const EXPAND = 'reading.expand';

    public const PDF = 'reading.export.pdf';

    public const EMAIL = 'reading.export.email';

    public const SHARE = 'reading.share';

    public const HISTORY = 'readings.history';

    public const ASTROMOTTO = 'astromotto';

    public const CREDITS_PAGE = 'credits.packages';

    public const CREDITS_REQUEST = 'credits.request';

    public const CREDITS_PROMO = 'credits.promo';

    public const ACCOUNT = 'account.show';

    public const PACKS_CHOOSE = 'packs.choose';

    public const PACKS_BACKS = 'packs.backs';

    public const LOGIC_AND = 'and';

    public const LOGIC_OR = 'or';

    /**
     * @return array<string, array{charges: bool}>
     */
    public static function all(): array
    {
        return [
            self::READING => ['charges' => true],
            self::CLARIFY => ['charges' => true],
            self::EXPAND => ['charges' => true],
            self::PDF => ['charges' => true],
            self::EMAIL => ['charges' => true],
            self::SHARE => ['charges' => true],
            self::HISTORY => ['charges' => false],
            self::ASTROMOTTO => ['charges' => false],
            self::CREDITS_PAGE => ['charges' => false],
            self::CREDITS_REQUEST => ['charges' => true],
            self::CREDITS_PROMO => ['charges' => false],
            self::ACCOUNT => ['charges' => false],
            self::PACKS_CHOOSE => ['charges' => false],
            self::PACKS_BACKS => ['charges' => false],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function charges(string $key): bool
    {
        return (bool) (self::all()[$key]['charges'] ?? false);
    }

    /**
     * Package-card perks that can be ticked in the package editor.
     *
     * @return list<string>
     */
    public static function packagePerks(): array
    {
        return [
            self::READING,
            self::CLARIFY,
            self::EXPAND,
            self::PDF,
            self::EMAIL,
            self::SHARE,
            self::HISTORY,
            self::ASTROMOTTO,
            self::PACKS_CHOOSE,
            self::PACKS_BACKS,
        ];
    }
}

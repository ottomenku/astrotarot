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

    public const LOGIC_AND = 'and';

    public const LOGIC_OR = 'or';

    /**
     * @return array<string, array{charges: bool, routes: list<string>}>
     */
    public static function all(): array
    {
        return [
            self::READING => [
                'charges' => true,
                'routes' => ['reading'],
            ],
            self::CLARIFY => [
                'charges' => true,
                'routes' => ['reading'],
            ],
            self::EXPAND => [
                'charges' => true,
                'routes' => ['reading'],
            ],
            self::PDF => [
                'charges' => true,
                'routes' => ['reading.export.pdf'],
            ],
            self::EMAIL => [
                'charges' => true,
                'routes' => ['reading.export.email'],
            ],
            self::SHARE => [
                'charges' => true,
                'routes' => ['reading.share.create'],
            ],
            self::HISTORY => [
                'charges' => false,
                'routes' => ['readings.history', 'readings.history.show'],
            ],
            self::ASTROMOTTO => [
                'charges' => false,
                'routes' => [],
            ],
            self::CREDITS_PAGE => [
                'charges' => false,
                'routes' => ['credits.packages'],
            ],
            self::CREDITS_REQUEST => [
                'charges' => true,
                'routes' => ['credits.request'],
            ],
            self::CREDITS_PROMO => [
                'charges' => false,
                'routes' => ['credits.promo'],
            ],
            self::ACCOUNT => [
                'charges' => false,
                'routes' => [
                    'account.show',
                    'account.readings.show',
                    'account.readings.destroy',
                    'account.readings.destroy-all',
                    'account.deletion-request.store',
                ],
            ],
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

    public static function help(string $key): string
    {
        $help = __('admin.feature_access.help');

        return is_array($help) ? (string) ($help[$key] ?? $key) : $key;
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
        ];
    }
}

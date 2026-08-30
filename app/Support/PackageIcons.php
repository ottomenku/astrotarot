<?php

namespace App\Support;

final class PackageIcons
{
    public const NONE = '';

    public const HEART = 'heart';

    public const SUN = 'sun';

    public const CROWN = 'crown';

    public const STAR = 'star';

    /** @var list<string> */
    public const ALL = [
        self::NONE,
        self::HEART,
        self::SUN,
        self::CROWN,
        self::STAR,
    ];

    public static function defaultForSlug(string $slug): string
    {
        return match ($slug) {
            'premium' => self::HEART,
            'pro' => self::SUN,
            'expert' => self::CROWN,
            default => self::NONE,
        };
    }

    public static function resolve(?string $icon, ?string $slug = null): string
    {
        $icon = (string) $icon;
        if ($icon !== self::NONE && in_array($icon, self::ALL, true)) {
            return $icon;
        }

        return $slug ? self::defaultForSlug($slug) : self::NONE;
    }

    public static function svg(string $icon, ?string $slug = null): string
    {
        $icon = self::resolve($icon, $slug);

        return match ($icon) {
            self::HEART => '<svg class="pkg-icon pkg-icon-heart" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 21s-6.54-4.2-9.2-8.05C.9 9.7 1.7 5.85 4.75 4.5 6.7 3.65 8.95 4.2 12 7.2c3.05-3 5.3-3.55 7.25-2.7 3.05 1.35 3.85 5.2 1.95 8.45C18.54 16.8 12 21 12 21z"/></svg>',
            self::SUN => '<svg class="pkg-icon pkg-icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4" fill="currentColor"/><g fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M12 2.4v2.7M12 18.9v2.7M4.93 4.93l1.9 1.9M17.17 17.17l1.9 1.9M2.4 12h2.7M18.9 12h2.7M4.93 19.07l1.9-1.9M17.17 6.83l1.9-1.9"/></g></svg>',
            self::CROWN => '<svg class="pkg-icon pkg-icon-crown" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3.5 17.5 5 8l4.2 4.6L12 6.5l2.8 6.1L19 8l1.5 9.5H3.5zM4 19h16v1.8H4V19z"/></svg>',
            self::STAR => '<svg class="pkg-icon pkg-icon-star" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="m12 3.2 2.35 6.1 6.55.35-5.1 4.05 1.7 6.3L12 16.7 6.5 20l1.7-6.3-5.1-4.05 6.55-.35L12 3.2z"/></svg>',
            default => '',
        };
    }
}

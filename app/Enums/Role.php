<?php

namespace App\Enums;

use App\Support\AdminIcons;
use App\Support\PackageIcons;

enum Role: string
{
    case User = 'user';
    case Pro = 'pro';
    case Expert = 'expert';
    case Admin = 'admin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::User => __('roles.user'),
            self::Pro => __('roles.pro'),
            self::Expert => __('roles.expert'),
            self::Admin => __('roles.admin'),
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function iconSvg(): string
    {
        return match ($this) {
            self::User => AdminIcons::user(),
            self::Pro => PackageIcons::svg(PackageIcons::SUN),
            self::Expert => PackageIcons::svg(PackageIcons::CROWN),
            self::Admin => PackageIcons::svg(PackageIcons::STAR),
        };
    }
}

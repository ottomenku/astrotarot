<?php

namespace App\Enums;

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
}

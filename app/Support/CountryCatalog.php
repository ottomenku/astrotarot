<?php

namespace App\Support;

class CountryCatalog
{
    /** @var list<array{code: string, hu: string, en: string}>|null */
    private static ?array $rows = null;

    /**
     * Country names from the stefangabos/world_countries dataset.
     *
     * @return list<array{code: string, name: string}>
     */
    public static function all(?string $locale = null): array
    {
        $locale = ($locale ?? app()->getLocale()) === 'en' ? 'en' : 'hu';
        $list = [];
        foreach (self::rows() as $row) {
            $name = trim((string) ($row[$locale] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['en'] ?? ''));
            }
            if ($name === '' || strlen((string) ($row['code'] ?? '')) !== 2) {
                continue;
            }
            $list[] = [
                'code' => strtoupper((string) $row['code']),
                'name' => $name,
            ];
        }

        usort($list, fn (array $a, array $b): int => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));

        return $list;
    }

    public static function nameFor(?string $name, ?string $locale = null): ?string
    {
        $code = self::codeFor($name);
        if ($code === null) {
            return null;
        }

        foreach (self::all($locale) as $row) {
            if ($row['code'] === $code) {
                return $row['name'];
            }
        }

        return null;
    }

    public static function codeFor(?string $name): ?string
    {
        $name = mb_strtolower(trim((string) $name));
        if ($name === '') {
            return null;
        }

        foreach (self::rows() as $row) {
            if (mb_strtolower((string) ($row['hu'] ?? '')) === $name || mb_strtolower((string) ($row['en'] ?? '')) === $name) {
                return strtoupper((string) $row['code']);
            }
        }

        return null;
    }

    public static function known(string $code): bool
    {
        $code = strtoupper(trim($code));
        foreach (self::rows() as $row) {
            if (strtoupper((string) ($row['code'] ?? '')) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{code: string, hu: string, en: string}>
     */
    private static function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }

        $decoded = json_decode((string) file_get_contents(resource_path('data/countries.json')), true);
        self::$rows = is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];

        return self::$rows;
    }
}

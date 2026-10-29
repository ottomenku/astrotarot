<?php

namespace App\Support;

class CelestialOracleCatalog
{
    /**
     * The 22 celestial oracles from the egi-orakulum deck.
     *
     * @var list<array{id: string, hu: string, en: string}>
     */
    public const ORACLES = [
        ['id' => 'sun', 'hu' => 'Nap', 'en' => 'Sun'],
        ['id' => 'moon', 'hu' => 'Hold', 'en' => 'Moon'],
        ['id' => 'mercury', 'hu' => 'Merkúr', 'en' => 'Mercury'],
        ['id' => 'venus', 'hu' => 'Vénusz', 'en' => 'Venus'],
        ['id' => 'mars', 'hu' => 'Mars', 'en' => 'Mars'],
        ['id' => 'jupiter', 'hu' => 'Jupiter', 'en' => 'Jupiter'],
        ['id' => 'saturn', 'hu' => 'Szaturnusz', 'en' => 'Saturn'],
        ['id' => 'uranus', 'hu' => 'Uránusz', 'en' => 'Uranus'],
        ['id' => 'neptune', 'hu' => 'Neptunusz', 'en' => 'Neptune'],
        ['id' => 'pluto', 'hu' => 'Plútó', 'en' => 'Pluto'],
        ['id' => 'aries', 'hu' => 'Kos', 'en' => 'Aries'],
        ['id' => 'taurus', 'hu' => 'Bika', 'en' => 'Taurus'],
        ['id' => 'gemini', 'hu' => 'Ikrek', 'en' => 'Gemini'],
        ['id' => 'cancer', 'hu' => 'Rák', 'en' => 'Cancer'],
        ['id' => 'leo', 'hu' => 'Oroszlán', 'en' => 'Leo'],
        ['id' => 'virgo', 'hu' => 'Szűz', 'en' => 'Virgo'],
        ['id' => 'libra', 'hu' => 'Mérleg', 'en' => 'Libra'],
        ['id' => 'scorpio', 'hu' => 'Skorpió', 'en' => 'Scorpio'],
        ['id' => 'sagittarius', 'hu' => 'Nyilas', 'en' => 'Sagittarius'],
        ['id' => 'capricorn', 'hu' => 'Bak', 'en' => 'Capricorn'],
        ['id' => 'aquarius', 'hu' => 'Vízöntő', 'en' => 'Aquarius'],
        ['id' => 'pisces', 'hu' => 'Halak', 'en' => 'Pisces'],
    ];

    /**
     * @return list<array{id: string, hu: string, en: string}>
     */
    public static function all(): array
    {
        return self::ORACLES;
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_column(self::ORACLES, 'id');
    }

    /**
     * Select values: none, random, and the 22 oracles.
     *
     * @return list<string>
     */
    public static function choices(): array
    {
        return array_merge(['none', 'random'], self::ids());
    }

    public static function known(string $id): bool
    {
        return in_array($id, self::ids(), true);
    }

    public static function label(string $id, string $locale = 'hu'): string
    {
        foreach (self::ORACLES as $oracle) {
            if ($oracle['id'] === $id) {
                return $locale === 'en' ? $oracle['en'] : $oracle['hu'];
            }
        }

        return $id;
    }
}

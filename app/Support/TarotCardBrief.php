<?php

namespace App\Support;

final class TarotCardBrief
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $byId = null;

    /**
     * @return list<string>
     */
    public static function lines(string $locale, string $figureId): array
    {
        $row = self::find($figureId);
        if ($row === null) {
            return [];
        }

        $en = $locale === 'en';
        $pairs = [
            [$en ? 'catalog' : 'katalógus', $row[$en ? 'meaning_en' : 'meaning_hu'] ?? ''],
            [$en ? 'description' : 'leírás', $row[$en ? 'description_en' : 'description_hu'] ?? ''],
            [$en ? 'light' : 'fény', $row[$en ? 'light_en' : 'light_hu'] ?? ''],
            [$en ? 'shadow' : 'árnyék', $row[$en ? 'shadow_en' : 'shadow_hu'] ?? ''],
            [$en ? 'life' : 'élet', $row[$en ? 'life_en' : 'life_hu'] ?? ''],
        ];

        $lines = [];
        foreach ($pairs as [$label, $value]) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function find(string $figureId): ?array
    {
        $id = trim($figureId);
        if ($id === '') {
            return null;
        }

        $row = self::index()[$id] ?? null;

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function index(): array
    {
        if (self::$byId !== null) {
            return self::$byId;
        }

        $byId = [];
        foreach ([
            resource_path('data/agent-knowledge/02-tarot-major-arcana.json'),
            resource_path('data/agent-knowledge/03-tarot-minor-arcana.json'),
        ] as $path) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $records = is_array($decoded['records'] ?? null) ? $decoded['records'] : [];
            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }
                $id = trim((string) ($record['id'] ?? ''));
                if ($id === '' || ($record['kind'] ?? '') === 'suit-overview') {
                    continue;
                }
                $byId[$id] = $record;
            }
        }

        return self::$byId = $byId;
    }
}

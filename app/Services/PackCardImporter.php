<?php

namespace App\Services;

use App\Models\CardPack;
use App\Models\MeaningEntry;
use Illuminate\Support\Str;

class PackCardImporter
{
    public function importMissing(): int
    {
        $created = 0;
        CardPack::query()->figure()->orderBy('id')->each(function (CardPack $pack) use (&$created) {
            if ($pack->cards()->exists()) {
                return;
            }
            $created += $this->importPack($pack);
        });

        return $created;
    }

    public function importPack(CardPack $pack): int
    {
        $entries = $pack->meaning_group_id
            ? MeaningEntry::query()->where('meaning_group_id', $pack->meaning_group_id)->get()->keyBy('figure_id')
            : collect();
        $deck = $this->deck($pack->slug);
        $figures = is_array($deck['figures'] ?? null) ? $deck['figures'] : [];
        $rows = $figures !== []
            ? $this->rowsFromDeck($pack->slug, $figures, $entries)
            : $this->rowsFromFiles($pack->slug, $entries);

        foreach ($rows as $row) {
            $pack->cards()->create($row);
        }

        return count($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function deck(string $slug): array
    {
        $path = public_path('card-pack/'.$slug.'/deck.json');
        if (! is_file($path)) {
            return [];
        }
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<array<string, mixed>>  $figures
     * @param  \Illuminate\Support\Collection<string, MeaningEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function rowsFromDeck(string $slug, array $figures, $entries): array
    {
        $rows = [];
        $seen = [];
        foreach ($figures as $figure) {
            if (! is_array($figure)) {
                continue;
            }
            $id = Str::slug((string) ($figure['id'] ?? ''), '-');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $entry = $entries->get($id);
            $keyword = is_array($figure['keyword'] ?? null) ? $figure['keyword'] : [];
            $blurb = is_array($figure['blurb'] ?? null) ? $figure['blurb'] : [];
            $nameHu = trim((string) ($figure['hu'] ?? ''));
            $nameEn = trim((string) ($figure['en'] ?? ''));
            $rows[] = $this->row(
                $id,
                count($rows),
                $entry instanceof MeaningEntry ? $entry->id : null,
                $nameHu !== '' ? $nameHu : ($entry?->name_hu ?: $id),
                $nameEn !== '' ? $nameEn : ($entry?->name_en ?: $nameHu ?: $id),
                (string) ($keyword['hu'] ?? $entry?->meaning_hu ?? ''),
                (string) ($keyword['en'] ?? $entry?->meaning_en ?? ''),
                (string) ($blurb['hu'] ?? $entry?->description_hu ?? ''),
                (string) ($blurb['en'] ?? $entry?->description_en ?? ''),
                trim((string) ($figure['kind'] ?? '')) ?: null,
                $this->imageFor($slug, $id),
            );
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, MeaningEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function rowsFromFiles(string $slug, $entries): array
    {
        $root = public_path('card-pack/'.$slug.'/figures');
        if (! is_dir($root)) {
            return [];
        }
        $groups = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                continue;
            }
            $id = $this->figureIdFromFilename($file->getFilename());
            $relative = 'card-pack/'.$slug.'/'.ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(public_path('card-pack/'.$slug)))), '/');
            $groups[$id][] = ['filename' => $file->getFilename(), 'path' => $relative];
        }

        $rows = [];
        foreach ($groups as $id => $files) {
            $chosen = $files[0];
            foreach ($files as $file) {
                if (! preg_match('/-(light|shadow)\.[a-z]+$/i', $file['filename'])) {
                    $chosen = $file;
                    break;
                }
            }
            $entry = $entries->get($id);
            $label = Str::headline(str_replace('-', ' ', (string) $id));
            $rows[] = $this->row(
                (string) $id,
                count($rows),
                $entry instanceof MeaningEntry ? $entry->id : null,
                $entry?->name_hu ?: $label,
                $entry?->name_en ?: $label,
                (string) ($entry?->meaning_hu ?? ''),
                (string) ($entry?->meaning_en ?? ''),
                (string) ($entry?->description_hu ?? ''),
                (string) ($entry?->description_en ?? ''),
                null,
                $chosen['path'],
            );
        }

        return $rows;
    }

    private function imageFor(string $slug, string $id): ?string
    {
        foreach ([
            "figures/{$id}.png",
            "figures/{$id}.jpg",
            "figures/{$id}.jpeg",
            "figures/{$id}.webp",
            "figures/{$id}-light.png",
            "figures/{$id}-light.jpg",
            "figures/{$id}-light.webp",
            "figures/minors/{$id}.png",
            "figures/{$id}-shadow.png",
        ] as $relative) {
            if (is_file(public_path('card-pack/'.$slug.'/'.$relative))) {
                return 'card-pack/'.$slug.'/'.$relative;
            }
        }

        return null;
    }

    private function figureIdFromFilename(string $filename): string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = preg_replace('/[-_](light|shadow)$/i', '', (string) $stem) ?? $stem;
        $id = Str::slug((string) $stem, '-');

        return $id !== '' ? $id : 'card';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $id,
        int $order,
        ?int $entryId,
        string $nameHu,
        string $nameEn,
        string $keywordHu,
        string $keywordEn,
        string $descriptionHu,
        string $descriptionEn,
        ?string $kind,
        ?string $image,
    ): array {
        return [
            'figure_id' => Str::limit($id, 80, ''),
            'meaning_entry_id' => $entryId,
            'sort_order' => $order,
            'name_hu' => Str::limit($nameHu, 160, ''),
            'name_en' => Str::limit($nameEn, 160, ''),
            'keyword_hu' => Str::limit(trim($keywordHu), 160, '') ?: null,
            'keyword_en' => Str::limit(trim($keywordEn), 160, '') ?: null,
            'description_hu' => trim($descriptionHu) ?: null,
            'description_en' => trim($descriptionEn) ?: null,
            'kind' => $kind !== null ? Str::limit($kind, 32, '') : null,
            'image_path' => $image,
        ];
    }
}

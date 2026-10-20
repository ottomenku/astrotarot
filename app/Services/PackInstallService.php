<?php

namespace App\Services;

use App\Models\CardPack;
use App\Support\ZipBundle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PackInstallService
{
    private const MAX_FILES = 400;

    private const MAX_BYTES = 80 * 1024 * 1024;

    /**
     * @param  array{slug: string, card_count: int, name_hu: string, name_en: string, description_hu: string, description_en: string, type: string}  $fields
     */
    public function install(array $fields, UploadedFile $archive): CardPack
    {
        $slug = Str::lower(trim($fields['slug']));
        $destination = public_path('card-pack'.DIRECTORY_SEPARATOR.$slug);
        if (is_dir($destination) || CardPack::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('admin.pack_install.slug_taken'),
            ]);
        }

        $temp = storage_path('app'.DIRECTORY_SEPARATOR.'pack-install-'.Str::lower(Str::random(12)));
        mkdir($temp, 0755, true);

        try {
            $placed = $this->extract($archive, $temp, $fields['type']);
            if ($placed === []) {
                throw ValidationException::withMessages([
                    'archive' => __('admin.pack_install.empty_archive'),
                ]);
            }

            $deck = $this->readDeck($temp);
            $rows = $deck !== null
                ? $this->rowsFromDeck($deck, $placed, $slug, $fields['type'])
                : $this->rowsFromFiles($placed, $slug, $fields['type']);

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'archive' => __('admin.pack_install.no_cards'),
                ]);
            }

            $pack = DB::transaction(function () use ($fields, $slug, $rows, $deck) {
                $preview = $rows[0]['image_path'] ?? null;
                $pack = CardPack::query()->create([
                    'slug' => $slug,
                    'name_hu' => $fields['name_hu'],
                    'name_en' => $fields['name_en'],
                    'description_hu' => $fields['description_hu'],
                    'description_en' => $fields['description_en'],
                    'type' => $fields['type'],
                    'card_count' => (int) $fields['card_count'],
                    'style' => is_string($deck['style'] ?? null) ? $deck['style'] : null,
                    'owner' => 'AstroTarot',
                    'preview_path' => $preview,
                    'asset_path' => $fields['type'] === CardPack::TYPE_CARD_BACK
                        ? ($preview ?: 'card-pack/'.$slug)
                        : 'card-pack/'.$slug,
                    'is_active' => true,
                    'is_default' => false,
                    'sort_order' => (int) CardPack::query()->max('sort_order') + 1,
                ]);

                foreach ($rows as $row) {
                    $pack->cards()->create($row);
                }

                return $pack;
            });

            $this->publish($temp, $destination);

            return $pack->loadCount('cards');
        } catch (ValidationException $exception) {
            $this->deleteTree($destination);
            throw $exception;
        } finally {
            $this->deleteTree($temp);
        }
    }

    /**
     * @return list<array{relative: string, filename: string}>
     */
    private function extract(UploadedFile $archive, string $temp, string $type): array
    {
        try {
            $files = ZipBundle::read((string) file_get_contents($archive->getRealPath()));
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'archive' => __('admin.pack_install.bad_zip'),
            ]);
        }

        $names = array_map(fn (array $file) => str_replace('\\', '/', $file['name']), $files);
        $strip = $this->commonRoot($names);
        $placed = [];
        $bytes = 0;

        foreach ($files as $file) {
            $name = str_replace('\\', '/', $file['name']);
            $relative = $strip !== '' && str_starts_with($name, $strip.'/')
                ? substr($name, strlen($strip) + 1)
                : $name;
            $target = $this->targetRelative($relative, $type);
            if ($target === null || isset($placed[$target])) {
                continue;
            }
            if (count($placed) >= self::MAX_FILES) {
                throw ValidationException::withMessages([
                    'archive' => __('admin.pack_install.too_many_files'),
                ]);
            }

            $contents = $file['contents'];
            $bytes += strlen($contents);
            if ($bytes > self::MAX_BYTES) {
                throw ValidationException::withMessages([
                    'archive' => __('admin.pack_install.too_large'),
                ]);
            }

            $path = $temp.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $target);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, $contents);
            $placed[$target] = ['relative' => $target, 'filename' => basename($target)];
        }

        return array_values($placed);
    }

    /**
     * @param  list<string>  $entries
     */
    private function commonRoot(array $entries): string
    {
        $roots = [];
        foreach ($entries as $entry) {
            $parts = explode('/', $entry);
            if (count($parts) < 2 || $parts[0] === '') {
                return '';
            }
            $roots[$parts[0]] = true;
        }

        if (count($roots) !== 1) {
            return '';
        }

        $root = (string) array_key_first($roots);

        return in_array($root, ['figures', 'card-back'], true) ? '' : $root;
    }

    private function targetRelative(string $relative, string $type): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '/')) {
            return null;
        }
        if (str_contains($relative, '__MACOSX/') || str_contains($relative, '.DS_Store')) {
            return null;
        }

        $base = basename($relative);
        if ($base === '' || $base === '.' || $base === '..' || str_starts_with($base, '.')) {
            return null;
        }

        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (strtolower($base) === 'deck.json') {
            return 'deck.json';
        }
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return null;
        }

        $lower = strtolower($relative);
        if (str_starts_with($lower, 'card-back/')) {
            return 'card-back/'.$base;
        }
        if (str_starts_with($lower, 'figures/')) {
            $rest = substr($relative, strlen('figures/'));
            $rest = $this->safeNested($rest);
            if ($type === CardPack::TYPE_CARD_BACK) {
                return 'card-back/'.$base;
            }

            return $rest === null ? null : 'figures/'.$rest;
        }
        if ($type === CardPack::TYPE_CARD_BACK || preg_match('/(back|hatlap|hátlap)/iu', $base) === 1) {
            return 'card-back/'.$base;
        }

        return 'figures/'.$base;
    }

    private function safeNested(string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $parts = array_values(array_filter(explode('/', $relative), fn (string $part) => $part !== ''));
        if ($parts === [] || in_array('..', $parts, true) || in_array('.', $parts, true)) {
            return null;
        }
        foreach ($parts as $part) {
            if (! preg_match('/^[A-Za-z0-9._-]+$/', $part)) {
                return null;
            }
        }

        return implode('/', $parts);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readDeck(string $temp): ?array
    {
        $path = $temp.DIRECTORY_SEPARATOR.'deck.json';
        if (! is_file($path)) {
            return null;
        }
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'archive' => __('admin.pack_install.bad_json'),
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $deck
     * @param  list<array{relative: string, filename: string}>  $placed
     * @return list<array<string, mixed>>
     */
    private function rowsFromDeck(array $deck, array $placed, string $slug, string $type): array
    {
        $figures = $deck['figures'] ?? null;
        if (! is_array($figures) || $figures === []) {
            return $this->rowsFromFiles($placed, $slug, $type);
        }

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
            $keyword = is_array($figure['keyword'] ?? null) ? $figure['keyword'] : [];
            $blurb = is_array($figure['blurb'] ?? null) ? $figure['blurb'] : [];
            $nameHu = trim((string) ($figure['hu'] ?? ''));
            $nameEn = trim((string) ($figure['en'] ?? ''));
            $rows[] = $this->row(
                $id,
                count($rows),
                $nameHu !== '' ? $nameHu : $id,
                $nameEn !== '' ? $nameEn : ($nameHu !== '' ? $nameHu : $id),
                (string) ($keyword['hu'] ?? ''),
                (string) ($keyword['en'] ?? ''),
                (string) ($blurb['hu'] ?? ''),
                (string) ($blurb['en'] ?? ''),
                trim((string) ($figure['kind'] ?? '')) ?: null,
                $this->imageForFigure($id, $placed, $slug),
            );
        }

        return $rows;
    }

    /**
     * @param  list<array{relative: string, filename: string}>  $placed
     * @return list<array<string, mixed>>
     */
    private function rowsFromFiles(array $placed, string $slug, string $type): array
    {
        $images = array_values(array_filter($placed, function (array $file) use ($type): bool {
            if (! $this->isImage($file['relative'])) {
                return false;
            }
            if ($type === CardPack::TYPE_CARD_BACK) {
                return str_starts_with($file['relative'], 'card-back/');
            }

            return str_starts_with($file['relative'], 'figures/');
        }));

        $groups = [];
        foreach ($images as $file) {
            $id = $this->figureIdFromFilename($file['filename']);
            $groups[$id][] = $file;
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
            $label = Str::headline(str_replace('-', ' ', (string) $id));
            $rows[] = $this->row(
                (string) $id,
                count($rows),
                $label,
                $label,
                '',
                '',
                '',
                '',
                null,
                'card-pack/'.$slug.'/'.$chosen['relative'],
            );
        }

        return $rows;
    }

    /**
     * @param  list<array{relative: string, filename: string}>  $placed
     */
    private function imageForFigure(string $id, array $placed, string $slug): ?string
    {
        $want = [
            "figures/{$id}.png",
            "figures/{$id}.jpg",
            "figures/{$id}.jpeg",
            "figures/{$id}.webp",
            "figures/{$id}.gif",
            "figures/{$id}-light.png",
            "figures/{$id}-light.jpg",
            "figures/{$id}-light.webp",
            "figures/minors/{$id}.png",
            "figures/{$id}-shadow.png",
            "card-back/{$id}.png",
        ];
        $byRelative = [];
        foreach ($placed as $file) {
            $byRelative[strtolower($file['relative'])] = $file['relative'];
        }
        foreach ($want as $candidate) {
            $found = $byRelative[strtolower($candidate)] ?? null;
            if ($found !== null) {
                return 'card-pack/'.$slug.'/'.$found;
            }
        }
        foreach ($placed as $file) {
            $stem = strtolower(pathinfo($file['filename'], PATHINFO_FILENAME));
            if ($stem === $id || $stem === $id.'-light' || $stem === $id.'-shadow') {
                return 'card-pack/'.$slug.'/'.$file['relative'];
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

    private function isImage(string $relative): bool
    {
        return in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $id,
        int $order,
        string $nameHu,
        string $nameEn,
        string $keywordHu,
        string $keywordEn,
        string $descriptionHu,
        string $descriptionEn,
        ?string $kind,
        ?string $imagePath,
    ): array {
        return [
            'figure_id' => Str::limit($id, 80, ''),
            'sort_order' => $order,
            'name_hu' => Str::limit($nameHu, 160, ''),
            'name_en' => Str::limit($nameEn, 160, ''),
            'keyword_hu' => Str::limit(trim($keywordHu), 160, '') ?: null,
            'keyword_en' => Str::limit(trim($keywordEn), 160, '') ?: null,
            'description_hu' => trim($descriptionHu) ?: null,
            'description_en' => trim($descriptionEn) ?: null,
            'kind' => $kind !== null ? Str::limit($kind, 32, '') : null,
            'image_path' => $imagePath,
        ];
    }

    private function publish(string $temp, string $destination): void
    {
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }
        if (! @rename($temp, $destination)) {
            $this->copyTree($temp, $destination);
            $this->deleteTree($temp);
        }
    }

    private function copyTree(string $from, string $to): void
    {
        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }
        $items = scandir($from) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $source = $from.DIRECTORY_SEPARATOR.$item;
            $target = $to.DIRECTORY_SEPARATOR.$item;
            if (is_dir($source)) {
                $this->copyTree($source, $target);
            } else {
                copy($source, $target);
            }
        }
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;
use App\Models\PackCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CardPackController extends Controller
{
    public function index(): View
    {
        return view('admin.packs.index', [
            'packs' => CardPack::query()->ordered()->orderBy('type')->get()->groupBy('type'),
            'types' => CardPack::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $type = (string) $request->query('type', CardPack::TYPE_TAROT);
        if (! in_array($type, CardPack::TYPES, true)) {
            $type = CardPack::TYPE_TAROT;
        }

        return view('admin.packs.form', $this->formData(new CardPack([
            'type' => $type,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => (int) CardPack::query()->max('sort_order') + 1,
            'card_count' => $type === CardPack::TYPE_TAROT || $type === CardPack::TYPE_ORACLE ? 0 : 1,
            'owner' => 'AstroTarot',
            'meaning_group_id' => MeaningGroup::query()
                ->where('type', $type)
                ->whereIn('slug', $type === CardPack::TYPE_ORACLE ? ['base-orakulum'] : ['base-tarot'])
                ->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['preview'], $data['asset']);

        $pack = CardPack::query()->create($data);
        $this->storeFiles($request, $pack);

        if ($pack->is_default) {
            $pack->makeDefault();
        }

        return redirect()
            ->route('admin.packs.edit', $pack)
            ->with('status', __('admin.packs.saved'));
    }

    public function edit(CardPack $pack): View
    {
        return view('admin.packs.form', $this->formData($pack));
    }

    public function update(Request $request, CardPack $pack): RedirectResponse
    {
        $data = $this->validated($request, $pack);
        unset($data['preview'], $data['asset'], $data['cards']);

        $pack->update($data);
        $pack = $pack->fresh();
        $this->storeFiles($request, $pack);
        $this->syncCards($request, $pack);

        if ($pack->fresh()->is_default) {
            $pack->fresh()->makeDefault();
        }

        return redirect()
            ->route('admin.packs.edit', $pack)
            ->with('status', __('admin.packs.saved'));
    }

    public function toggle(CardPack $pack): RedirectResponse
    {
        $pack->update(['is_active' => ! $pack->is_active]);

        return redirect()
            ->route('admin.packs.index')
            ->with('status', $pack->is_active
                ? __('admin.packs.activated')
                : __('admin.packs.deactivated'));
    }

    public function makeDefault(CardPack $pack): RedirectResponse
    {
        $pack->makeDefault();

        return redirect()
            ->route('admin.packs.index')
            ->with('status', __('admin.packs.default_set'));
    }

    public function destroy(CardPack $pack): RedirectResponse
    {
        $pack->delete();

        return redirect()
            ->route('admin.packs.index')
            ->with('status', __('admin.packs.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CardPack $pack): array
    {
        if ($pack->exists) {
            $pack->load(['cards.meaningEntry']);
        }

        return [
            'pack' => $pack,
            'backs' => CardPack::query()->ofType(CardPack::TYPE_CARD_BACK)->ordered()->get(),
            'meaningGroups' => MeaningGroup::query()->orderBy('name_hu')->get(),
            'meaningCatalog' => MeaningEntry::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('meaning_group_id')
                ->map(fn ($entries) => $entries->map(fn (MeaningEntry $entry) => [
                    'id' => $entry->id,
                    'figure_id' => $entry->figure_id,
                    'name_hu' => $entry->name_hu,
                    'name_en' => $entry->name_en,
                    'meaning_hu' => $entry->meaning_hu,
                    'meaning_en' => $entry->meaning_en,
                    'description_hu' => $entry->description_hu,
                    'description_en' => $entry->description_en,
                ])->values())
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CardPack $pack = null): array
    {
        $type = (string) $request->input('type', $pack?->type ?? CardPack::TYPE_TAROT);

        $data = $request->validate([
            'name_hu' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('card_packs', 'slug')->ignore($pack?->id),
            ],
            'description_hu' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(CardPack::TYPES)],
            'card_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'cards' => ['nullable', 'array'],
            'cards.*.meaning_entry_id' => ['nullable', 'integer'],
            'cards.*.name_hu' => ['nullable', 'string', 'max:160'],
            'cards.*.name_en' => ['nullable', 'string', 'max:160'],
            'cards.*.keyword_hu' => ['nullable', 'string', 'max:160'],
            'cards.*.keyword_en' => ['nullable', 'string', 'max:160'],
            'cards.*.description_hu' => ['nullable', 'string', 'max:5000'],
            'cards.*.description_en' => ['nullable', 'string', 'max:5000'],
            'cards.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'style' => ['nullable', 'string', 'max:120'],
            'owner' => ['nullable', 'string', 'max:120'],
            'preview' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'asset' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'default_card_back_id' => [
                'nullable',
                'integer',
                Rule::exists('card_packs', 'id')->where('type', CardPack::TYPE_CARD_BACK),
            ],
            'meaning_group_id' => [
                'nullable',
                'integer',
                Rule::exists('meaning_groups', 'id')->when(
                    in_array($type, CardPack::FIGURE_TYPES, true),
                    fn ($q) => $q->where('type', $type),
                ),
            ],
        ]);

        $data['slug'] = trim((string) ($data['slug'] ?? ''));
        if ($data['slug'] === '') {
            $data['slug'] = Str::slug($data['name_hu']);
        }
        if ($data['slug'] === '') {
            $data['slug'] = 'pack-'.Str::lower(Str::random(6));
        }
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');
        $data['description_hu'] = (string) ($data['description_hu'] ?? '');
        $data['description_en'] = (string) ($data['description_en'] ?? '');
        $data['style'] = trim((string) ($data['style'] ?? '')) ?: null;
        $data['owner'] = trim((string) ($data['owner'] ?? '')) ?: null;
        $data['card_count'] = (int) ($data['card_count'] ?? 0);

        if (! in_array($data['type'], CardPack::FIGURE_TYPES, true)) {
            $data['default_card_back_id'] = null;
            $data['meaning_group_id'] = null;
        } else {
            $data['meaning_group_id'] = $data['meaning_group_id'] ?? null;
        }

        if ($data['type'] === CardPack::TYPE_CARD_BACK) {
            $data['card_count'] = max(1, $data['card_count']);
        }

        return $data;
    }

    private function storeFiles(Request $request, CardPack $pack): void
    {
        $dir = public_path('card-pack/'.$pack->slug);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($pack->isFigure() && $pack->asset_path === null) {
            foreach (['figures', 'card-back'] as $sub) {
                $path = $dir.DIRECTORY_SEPARATOR.$sub;
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            }
            $pack->update(['asset_path' => 'card-pack/'.$pack->slug]);
        }

        if ($request->hasFile('preview')) {
            $relative = $this->moveUpload($request->file('preview'), $dir, 'preview');
            if (is_string($pack->preview_path) && $pack->preview_path !== $relative && is_file(public_path($pack->preview_path))) {
                @unlink(public_path($pack->preview_path));
            }
            $pack->update(['preview_path' => $relative]);
        }

        if ($request->hasFile('asset') && $pack->type === CardPack::TYPE_CARD_BACK) {
            $backDir = $dir.DIRECTORY_SEPARATOR.'card-back';
            if (! is_dir($backDir)) {
                mkdir($backDir, 0755, true);
            }
            $relative = $this->moveUpload($request->file('asset'), $backDir, 'card-back');
            $pack->update([
                'asset_path' => $relative,
                'preview_path' => $pack->preview_path ?: $relative,
            ]);
        }
    }

    private function moveUpload(UploadedFile $file, string $dir, string $basename): string
    {
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'png';
        }
        $name = $basename.'.'.$ext;
        $file->move($dir, $name);
        $publicRoot = public_path();
        $full = $dir.DIRECTORY_SEPARATOR.$name;

        return ltrim(str_replace('\\', '/', substr($full, strlen($publicRoot))), '/');
    }

    private function syncCards(Request $request, CardPack $pack): void
    {
        $rows = $request->input('cards');
        if (! is_array($rows)) {
            return;
        }

        $allowed = $pack->meaning_group_id
            ? array_map('intval', MeaningEntry::query()->where('meaning_group_id', $pack->meaning_group_id)->pluck('id')->all())
            : [];

        foreach ($rows as $id => $row) {
            if (! is_array($row)) {
                continue;
            }
            $card = $pack->cards()->whereKey((int) $id)->first();
            if (! $card instanceof PackCard) {
                continue;
            }
            $entryId = (int) ($row['meaning_entry_id'] ?? 0);
            $nameHu = trim((string) ($row['name_hu'] ?? ''));
            $nameEn = trim((string) ($row['name_en'] ?? ''));
            $card->update([
                'meaning_entry_id' => in_array($entryId, $allowed, true) ? $entryId : null,
                'name_hu' => $nameHu !== '' ? $nameHu : $card->name_hu,
                'name_en' => $nameEn !== '' ? $nameEn : $card->name_en,
                'keyword_hu' => trim((string) ($row['keyword_hu'] ?? '')) ?: null,
                'keyword_en' => trim((string) ($row['keyword_en'] ?? '')) ?: null,
                'description_hu' => trim((string) ($row['description_hu'] ?? '')) ?: null,
                'description_en' => trim((string) ($row['description_en'] ?? '')) ?: null,
            ]);
            $upload = $request->file('cards.'.$id.'.image');
            if ($upload instanceof UploadedFile) {
                $this->replaceCardImage($upload, $card->fresh());
            }
        }
    }

    private function replaceCardImage(UploadedFile $file, PackCard $card): void
    {
        $pack = $card->pack;
        if (! $pack instanceof CardPack) {
            return;
        }
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'png';
        }

        $current = str_replace('\\', '/', (string) $card->image_path);
        $prefix = 'card-pack/'.$pack->slug.'/';
        if ($current !== '' && str_starts_with($current, $prefix) && is_file(public_path($current))) {
            $dir = public_path(dirname($current));
            $base = pathinfo($current, PATHINFO_FILENAME);
            $previous = public_path($current);
            $file->move($dir, $base.'.'.$ext);
            $saved = $dir.DIRECTORY_SEPARATOR.$base.'.'.$ext;
            if (is_file($previous) && realpath($previous) !== realpath($saved)) {
                @unlink($previous);
            }
            $card->update([
                'image_path' => ltrim(str_replace('\\', '/', substr($saved, strlen(public_path()))), '/'),
            ]);

            return;
        }

        $dir = public_path('card-pack/'.$pack->slug.'/figures');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $card->figure_id.'.'.$ext);
        $card->update(['image_path' => 'card-pack/'.$pack->slug.'/figures/'.$card->figure_id.'.'.$ext]);
    }
}

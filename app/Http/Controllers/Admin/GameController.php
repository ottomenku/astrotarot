<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardPack;
use App\Models\Game;
use App\Models\GameRule;
use App\Support\CardPackCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        return view('admin.games.index', [
            'games' => Game::query()->withCount('rules')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.games.form', [
            'game' => new Game([
                'has_question' => true,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => (int) Game::query()->max('sort_order') + 1,
            ]),
            'rules' => [],
            'packs' => $this->figurePacks(),
            'backs' => $this->cardBacks(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $rules = $data['rules'];
        unset($data['rules'], $data['icon']);

        $game = Game::query()->create($data);
        $this->syncRules($game, $rules);
        $this->storeIcon($request, $game);

        if ($game->is_default) {
            $this->applyDefault($game);
        }

        return redirect()
            ->route('admin.games.edit', $game)
            ->with('status', __('admin.games.saved'));
    }

    public function edit(Game $game): View
    {
        $game->load(['rules' => fn ($query) => $query->ordered()]);

        return view('admin.games.form', [
            'game' => $game,
            'rules' => old('rules', $game->rules->map(fn (GameRule $rule) => [
                'pack_slug' => $rule->pack_slug,
                'pack_type' => $rule->pack_type ?: CardPackCatalog::typeForSlug($rule->pack_slug),
                'default_pack_id' => $rule->default_pack_id,
                'title_hu' => $rule->title_hu,
                'title_en' => $rule->title_en,
                'slot_key' => $rule->slot_key,
                'description_hu' => $rule->description_hu,
                'description_en' => $rule->description_en,
                'card_back_id' => $rule->card_back_id,
                'info_mode' => $rule->info_mode,
                'row' => $rule->row,
                'place' => $rule->place,
                'draw_order' => $rule->draw_order,
            ])->all()),
            'packs' => $this->figurePacks(),
            'backs' => $this->cardBacks(),
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $data = $this->validated($request, $game);
        $rules = $data['rules'];
        unset($data['rules'], $data['icon']);

        $game->update($data);
        $this->syncRules($game, $rules);
        $this->storeIcon($request, $game);

        if ($game->is_default) {
            $this->applyDefault($game);
        }

        return redirect()
            ->route('admin.games.edit', $game)
            ->with('status', __('admin.games.saved'));
    }

    public function toggle(Game $game): RedirectResponse
    {
        $game->update(['is_active' => ! $game->is_active]);

        return redirect()
            ->route('admin.games.index')
            ->with('status', $game->is_active
                ? __('admin.games.activated')
                : __('admin.games.deactivated'));
    }

    public function makeDefault(Game $game): RedirectResponse
    {
        $this->applyDefault($game);

        return redirect()
            ->route('admin.games.index')
            ->with('status', __('admin.games.default_set'));
    }

    private function applyDefault(Game $game): void
    {
        $game->makeDefault();

        $spread = session('spread');
        if (! is_array($spread) || ! array_key_exists('game', $spread)) {
            return;
        }

        unset($spread['game']);
        session(['spread' => $spread]);
    }

    public function destroy(Game $game): RedirectResponse
    {
        $wasDefault = $game->is_default;
        $game->delete();

        if ($wasDefault) {
            Game::query()->active()->ordered()->first()?->makeDefault();
        }

        return redirect()
            ->route('admin.games.index')
            ->with('status', __('admin.games.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Game $game = null): array
    {
        $data = $request->validate([
            'name_hu' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('games', 'slug')->ignore($game?->id),
            ],
            'description_hu' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'has_question' => ['sometimes', 'boolean'],
            'prompt_hu' => ['nullable', 'string', 'max:20000'],
            'prompt_en' => ['nullable', 'string', 'max:20000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'rules' => ['required', 'array', 'min:1', 'max:49'],
            'rules.*.pack_type' => ['nullable', 'string', Rule::in(CardPack::FIGURE_TYPES)],
            'rules.*.default_pack_id' => [
                'nullable',
                'integer',
                Rule::exists('card_packs', 'id')->whereIn('type', CardPack::FIGURE_TYPES),
            ],
            'rules.*.pack_slug' => ['nullable', 'string', Rule::in(CardPackCatalog::slugs())],
            'rules.*.title_hu' => ['required', 'string', 'max:120'],
            'rules.*.title_en' => ['required', 'string', 'max:120'],
            'rules.*.slot_key' => ['nullable', 'string', 'max:64', 'alpha_dash'],
            'rules.*.description_hu' => ['nullable', 'string', 'max:2000'],
            'rules.*.description_en' => ['nullable', 'string', 'max:2000'],
            'rules.*.card_back_id' => [
                'nullable',
                'integer',
                Rule::exists('card_packs', 'id')->where('type', CardPack::TYPE_CARD_BACK),
            ],
            'rules.*.info_mode' => ['nullable', 'string', Rule::in(GameRule::INFO_MODES)],
            'rules.*.row' => ['required', 'integer', 'min:1', 'max:12'],
            'rules.*.place' => ['required', 'integer', 'min:1', 'max:7'],
            'rules.*.draw_order' => ['nullable', 'integer', 'min:1', 'max:49'],
        ]);

        $data['slug'] = trim((string) ($data['slug'] ?? ''));
        if ($data['slug'] === '') {
            $data['slug'] = Str::slug($data['name_hu']);
        }
        if ($data['slug'] === '') {
            $data['slug'] = 'jatek-'.Str::lower(Str::random(6));
        }
        $data['has_question'] = $request->boolean('has_question');
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');
        $data['prompt_hu'] = (string) ($data['prompt_hu'] ?? '');
        $data['prompt_en'] = (string) ($data['prompt_en'] ?? '');
        $data['description_hu'] = (string) ($data['description_hu'] ?? '');
        $data['description_en'] = (string) ($data['description_en'] ?? '');

        $data['rules'] = $this->assertRuleLayout($data['rules']);

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function assertRuleLayout(array $rules): array
    {
        $seen = [];
        $seenKeys = [];
        $seenOrders = [];
        $perRow = [];
        foreach ($rules as $index => $rule) {
            $row = (int) $rule['row'];
            $place = (int) $rule['place'];
            $key = $row.':'.$place;
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "rules.{$index}.place" => __('admin.games.duplicate_place'),
                ]);
            }
            $seen[$key] = true;
            $order = (int) ($rule['draw_order'] ?? 0);
            if ($order < 1) {
                $order = $index + 1;
            }
            if (isset($seenOrders[$order])) {
                throw ValidationException::withMessages([
                    "rules.{$index}.draw_order" => __('admin.games.duplicate_draw'),
                ]);
            }
            $seenOrders[$order] = true;
            $rules[$index]['draw_order'] = $order;
            $slotKey = trim((string) ($rule['slot_key'] ?? ''));
            if ($slotKey !== '') {
                $slotKey = Str::lower($slotKey);
                if (isset($seenKeys[$slotKey])) {
                    throw ValidationException::withMessages([
                        "rules.{$index}.slot_key" => __('admin.games.duplicate_slot_key'),
                    ]);
                }
                $seenKeys[$slotKey] = true;
            }
            $perRow[$row] = ($perRow[$row] ?? 0) + 1;
            if ($perRow[$row] > 7) {
                throw ValidationException::withMessages([
                    "rules.{$index}.place" => __('admin.games.max_places'),
                ]);
            }
        }

        return $rules;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function syncRules(Game $game, array $rules): void
    {
        $game->rules()->delete();
        foreach ($rules as $rule) {
            $game->rules()->create($this->normalizeRule($rule));
        }
    }

    private function storeIcon(Request $request, Game $game): void
    {
        if (! $request->hasFile('icon')) {
            return;
        }

        $file = $request->file('icon');
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'png';
        }

        $dir = public_path('games');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = $game->slug.'.'.$ext;
        if (is_string($game->icon) && $game->icon !== '' && is_file(public_path($game->icon))) {
            @unlink(public_path($game->icon));
        }
        $file->move($dir, $name);
        $game->update(['icon' => 'games/'.$name]);
    }

    /**
     * @return list<array{id: int, slug: string, type: string, name: string}>
     */
    private function figurePacks(): array
    {
        return CardPack::query()->figure()->ordered()->get()->map(fn (CardPack $pack) => [
            'id' => $pack->id,
            'slug' => $pack->slug,
            'type' => $pack->type,
            'name' => $pack->name_hu,
        ])->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function cardBacks(): array
    {
        return CardPack::query()->ofType(CardPack::TYPE_CARD_BACK)->ordered()->get()->map(fn (CardPack $pack) => [
            'id' => $pack->id,
            'name' => $pack->name_hu,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function normalizeRule(array $rule): array
    {
        $pack = null;
        $packId = (int) ($rule['default_pack_id'] ?? 0);
        $slug = trim((string) ($rule['pack_slug'] ?? ''));
        $type = trim((string) ($rule['pack_type'] ?? ''));

        $explicit = $packId > 0 || $slug !== '';

        if ($packId > 0) {
            $pack = CardPack::query()->figure()->find($packId);
        }
        if ($pack === null && $slug !== '') {
            $pack = CardPack::query()->figure()->where('slug', CardPackCatalog::canonicalize($slug))->first();
        }

        if ($pack !== null && $explicit) {
            $type = $pack->type;
            $slug = $pack->slug;
            $packId = $pack->id;
        } else {
            if (! in_array($type, CardPack::FIGURE_TYPES, true)) {
                $type = CardPackCatalog::typeForSlug($slug) ?: CardPack::TYPE_TAROT;
            }
            $fallback = CardPack::defaultFor($type);
            $slug = $fallback?->slug ?? ($slug !== '' ? $slug : 'tarot');
            $packId = null;
        }

        $infoMode = (string) ($rule['info_mode'] ?? '');
        if (! in_array($infoMode, GameRule::INFO_MODES, true)) {
            $infoMode = $type === CardPack::TYPE_ORACLE ? GameRule::INFO_ASTRO : GameRule::INFO_MEANING;
        }

        $backId = (int) ($rule['card_back_id'] ?? 0);
        $slotKey = trim((string) ($rule['slot_key'] ?? ''));

        return [
            'pack_slug' => $slug,
            'pack_type' => $type,
            'default_pack_id' => $packId,
            'title_hu' => $rule['title_hu'],
            'title_en' => $rule['title_en'],
            'slot_key' => $slotKey !== '' ? $slotKey : null,
            'description_hu' => trim((string) ($rule['description_hu'] ?? '')) ?: null,
            'description_en' => trim((string) ($rule['description_en'] ?? '')) ?: null,
            'card_back_id' => $backId > 0 ? $backId : null,
            'info_mode' => $infoMode,
            'row' => (int) $rule['row'],
            'place' => (int) $rule['place'],
            'draw_order' => (int) $rule['draw_order'],
        ];
    }
}

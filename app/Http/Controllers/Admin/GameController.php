<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'packs' => CardPackCatalog::slugs(),
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
            $game->makeDefault();
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
                'title_hu' => $rule->title_hu,
                'title_en' => $rule->title_en,
                'info_mode' => $rule->info_mode,
                'row' => $rule->row,
                'place' => $rule->place,
            ])->all()),
            'packs' => CardPackCatalog::slugs(),
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
            $game->makeDefault();
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
        $game->makeDefault();

        return redirect()
            ->route('admin.games.index')
            ->with('status', __('admin.games.default_set'));
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
            'rules.*.pack_slug' => ['required', 'string', Rule::in(CardPackCatalog::slugs())],
            'rules.*.title_hu' => ['required', 'string', 'max:120'],
            'rules.*.title_en' => ['required', 'string', 'max:120'],
            'rules.*.info_mode' => ['required', 'string', Rule::in(GameRule::INFO_MODES)],
            'rules.*.row' => ['required', 'integer', 'min:1', 'max:12'],
            'rules.*.place' => ['required', 'integer', 'min:1', 'max:7'],
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

        $this->assertRuleLayout($data['rules']);

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function assertRuleLayout(array $rules): void
    {
        $seen = [];
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
            $perRow[$row] = ($perRow[$row] ?? 0) + 1;
            if ($perRow[$row] > 7) {
                throw ValidationException::withMessages([
                    "rules.{$index}.place" => __('admin.games.max_places'),
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function syncRules(Game $game, array $rules): void
    {
        $game->rules()->delete();
        foreach ($rules as $rule) {
            $game->rules()->create([
                'pack_slug' => $rule['pack_slug'],
                'title_hu' => $rule['title_hu'],
                'title_en' => $rule['title_en'],
                'info_mode' => $rule['info_mode'],
                'row' => (int) $rule['row'],
                'place' => (int) $rule['place'],
            ]);
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
}

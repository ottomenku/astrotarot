<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeaningGroupController extends Controller
{
    public function index(): View
    {
        return view('admin.meaning-groups.index', [
            'groups' => MeaningGroup::query()
                ->with('owner')
                ->withCount(['entries', 'packs'])
                ->orderBy('type')
                ->orderBy('name_hu')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.meaning-groups.form', $this->formData(new MeaningGroup([
            'type' => CardPack::TYPE_TAROT,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $group = MeaningGroup::query()->create($this->validatedGroup($request));
        $this->syncEntries($group, $request);

        return redirect()
            ->route('admin.meaning-groups.edit', $group)
            ->with('status', __('admin.meaning_groups.saved'));
    }

    public function edit(MeaningGroup $meaningGroup): View
    {
        $meaningGroup->load(['entries', 'owner']);

        return view('admin.meaning-groups.form', $this->formData($meaningGroup));
    }

    public function update(Request $request, MeaningGroup $meaningGroup): RedirectResponse
    {
        $meaningGroup->update($this->validatedGroup($request, $meaningGroup));
        $this->syncEntries($meaningGroup, $request);

        return redirect()
            ->route('admin.meaning-groups.edit', $meaningGroup)
            ->with('status', __('admin.meaning_groups.saved'));
    }

    public function copy(MeaningGroup $meaningGroup): RedirectResponse
    {
        $meaningGroup->load('entries');
        $copy = $meaningGroup->replicateWithEntries(request()->user());

        return redirect()
            ->route('admin.meaning-groups.edit', $copy)
            ->with('status', __('admin.meaning_groups.copied', ['name' => $copy->name()]));
    }

    public function destroy(MeaningGroup $meaningGroup): RedirectResponse
    {
        $meaningGroup->delete();

        return redirect()
            ->route('admin.meaning-groups.index')
            ->with('status', __('admin.meaning_groups.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(MeaningGroup $group): array
    {
        return [
            'group' => $group,
            'owners' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'entries' => old('entries', $group->entries->map(fn (MeaningEntry $entry) => [
                'id' => $entry->id,
                'figure_id' => $entry->figure_id,
                'name_hu' => $entry->name_hu,
                'name_en' => $entry->name_en,
                'meaning_hu' => $entry->meaning_hu,
                'meaning_en' => $entry->meaning_en,
                'description_hu' => $entry->description_hu,
                'description_en' => $entry->description_en,
            ])->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedGroup(Request $request, ?MeaningGroup $group = null): array
    {
        $data = $request->validate([
            'name_hu' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('meaning_groups', 'slug')->ignore($group?->id),
            ],
            'description_hu' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(CardPack::FIGURE_TYPES)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.figure_id' => ['nullable', 'string', 'max:80'],
            'entries.*.name_hu' => ['nullable', 'string', 'max:120'],
            'entries.*.name_en' => ['nullable', 'string', 'max:120'],
            'entries.*.meaning_hu' => ['nullable', 'string', 'max:160'],
            'entries.*.meaning_en' => ['nullable', 'string', 'max:160'],
            'entries.*.description_hu' => ['nullable', 'string', 'max:2000'],
            'entries.*.description_en' => ['nullable', 'string', 'max:2000'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = MeaningGroup::uniqueSlug(Str::slug($data['name_hu']) ?: 'meaning-group');
        }

        return [
            'slug' => $slug,
            'name_hu' => $data['name_hu'],
            'name_en' => $data['name_en'],
            'description_hu' => $data['description_hu'] ?? '',
            'description_en' => $data['description_en'] ?? '',
            'type' => $data['type'],
            'owner_id' => $data['owner_id'] ?? null,
        ];
    }

    private function syncEntries(MeaningGroup $group, Request $request): void
    {
        $rows = $request->input('entries', []);
        if (! is_array($rows)) {
            $rows = [];
        }

        $keep = [];
        $order = 0;
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $figureId = Str::slug((string) ($row['figure_id'] ?? ''), '-');
            $nameHu = trim((string) ($row['name_hu'] ?? ''));
            if ($figureId === '' && $nameHu === '') {
                continue;
            }
            if ($figureId === '') {
                $figureId = MeaningGroup::uniqueSlug(Str::slug($nameHu) ?: 'card');
            }
            if (isset($seen[$figureId])) {
                $figureId = $figureId.'-'.$order;
            }
            $seen[$figureId] = true;

            $payload = [
                'figure_id' => $figureId,
                'sort_order' => $order,
                'name_hu' => $nameHu !== '' ? $nameHu : $figureId,
                'name_en' => trim((string) ($row['name_en'] ?? '')) ?: $nameHu,
                'meaning_hu' => trim((string) ($row['meaning_hu'] ?? '')),
                'meaning_en' => trim((string) ($row['meaning_en'] ?? '')),
                'description_hu' => trim((string) ($row['description_hu'] ?? '')),
                'description_en' => trim((string) ($row['description_en'] ?? '')),
            ];

            $id = (int) ($row['id'] ?? 0);
            $entry = $id > 0
                ? MeaningEntry::query()
                    ->where('meaning_group_id', $group->id)
                    ->whereKey($id)
                    ->first()
                : null;

            if ($entry) {
                $entry->update($payload);
            } else {
                $entry = $group->entries()->create($payload);
            }
            $keep[] = $entry->id;
            $order++;
        }

        MeaningEntry::query()
            ->where('meaning_group_id', $group->id)
            ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep), fn ($q) => $q)
            ->delete();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureAccess;
use App\Models\Package;
use App\Services\FeatureAccessService;
use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use App\Support\PackageIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', [
            'packages' => Package::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', [
            'package' => new Package([
                'period' => Package::PERIOD_MONTHLY,
                'is_active' => true,
                'sort_order' => (int) Package::query()->max('sort_order') + 1,
                'perk_keys' => [],
            ]),
            'perkKeys' => FeatureCatalog::packagePerks(),
            'perkLabels' => app(FeatureAccessService::class)->labelMap(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $package = Package::query()->create($this->validated($request));
        $this->syncFeatureAccess($package);

        return redirect()
            ->route('admin.packages.edit', $package)
            ->with('status', __('admin.packages.saved'));
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', [
            'package' => $package,
            'perkKeys' => FeatureCatalog::packagePerks(),
            'perkLabels' => app(FeatureAccessService::class)->labelMap(),
        ]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $package->update($this->validated($request, $package));
        $this->syncFeatureAccess($package);

        return redirect()
            ->route('admin.packages.edit', $package)
            ->with('status', __('admin.packages.saved'));
    }

    public function toggle(Package $package): RedirectResponse
    {
        $package->update(['is_active' => ! $package->is_active]);

        return redirect()
            ->route('admin.packages.index')
            ->with('status', $package->is_active
                ? __('admin.packages.activated')
                : __('admin.packages.deactivated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'name_hu' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('packages', 'slug')->ignore($package?->id),
            ],
            'price_huf' => ['required', 'integer', 'min:0', 'max:10000000'],
            'price_usd' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'period' => ['required', 'string', Rule::in(Package::PERIODS)],
            'credits' => ['required', 'integer', 'min:0', 'max:100000'],
            'body_hu' => ['nullable', 'string', 'max:20000'],
            'body_en' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'icon' => ['nullable', 'string', Rule::in(PackageIcons::ALL)],
            'feature_keys' => ['sometimes', 'array'],
            'feature_keys.*' => ['string', Rule::in(FeatureCatalog::packagePerks())],
            'can_export_pdf' => ['sometimes', 'boolean'],
            'can_view_history' => ['sometimes', 'boolean'],
            'has_astromotto' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name_hu']);
        if ($data['slug'] === '') {
            $data['slug'] = 'csomag-'.Str::lower(Str::random(6));
        }
        $data['icon'] = PackageIcons::resolve($data['icon'] ?? null, $data['slug']);

        $perkKeys = $this->perkKeysFromRequest($request);
        $data['perk_keys'] = $perkKeys;
        $data['is_active'] = $request->boolean('is_active');
        $data['price_usd'] = round((float) ($data['price_usd'] ?? 0), 2);
        $data['can_export_pdf'] = in_array(FeatureCatalog::PDF, $perkKeys, true);
        $data['can_view_history'] = in_array(FeatureCatalog::HISTORY, $perkKeys, true);
        $data['has_astromotto'] = in_array(FeatureCatalog::ASTROMOTTO, $perkKeys, true);
        $labels = app(FeatureAccessService::class)->labelMap();
        $data['body_hu'] = PackageBody::merge(
            (string) ($data['body_hu'] ?? ''),
            PackageBody::items('hu', (int) $data['credits'], $data['period'], $perkKeys, $labels),
        );
        $data['body_en'] = PackageBody::merge(
            (string) ($data['body_en'] ?? ''),
            PackageBody::items('en', (int) $data['credits'], $data['period'], $perkKeys, $labels),
        );
        unset($data['feature_keys']);

        return $data;
    }

    /**
     * @return list<string>
     */
    private function perkKeysFromRequest(Request $request): array
    {
        if ($request->has('feature_keys')) {
            return array_values(array_intersect(
                array_map('strval', (array) $request->input('feature_keys', [])),
                FeatureCatalog::packagePerks(),
            ));
        }

        $keys = [];
        if ($request->boolean('can_export_pdf')) {
            $keys[] = FeatureCatalog::PDF;
            $keys[] = FeatureCatalog::EMAIL;
        }
        if ($request->boolean('can_view_history')) {
            $keys[] = FeatureCatalog::HISTORY;
        }
        if ($request->boolean('has_astromotto')) {
            $keys[] = FeatureCatalog::ASTROMOTTO;
        }

        return array_values(array_unique($keys));
    }

    private function syncFeatureAccess(Package $package): void
    {
        $selected = $package->perk_keys ?? [];

        foreach (FeatureCatalog::packagePerks() as $key) {
            $rule = FeatureAccess::query()->with('packages')->where('key', $key)->first();
            if ($rule === null) {
                continue;
            }

            if ($rule->packages->isEmpty()) {
                continue;
            }

            if (in_array($key, $selected, true)) {
                $rule->packages()->syncWithoutDetaching([$package->id]);
            } else {
                $rule->packages()->detach($package->id);
            }
        }
    }
}

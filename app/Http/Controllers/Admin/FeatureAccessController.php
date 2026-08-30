<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureAccess;
use App\Models\Package;
use App\Services\FeatureAccessService;
use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeatureAccessController extends Controller
{
    public function __construct(private readonly FeatureAccessService $features) {}

    public function edit(): View
    {
        $this->features->syncCatalog();

        return view('admin.feature-access.edit', [
            'rules' => FeatureAccess::query()->with('packages')->get()->keyBy('key'),
            'packages' => Package::query()->ordered()->get(),
            'catalog' => FeatureCatalog::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = FeatureCatalog::keys();
        $data = $request->validate([
            'features' => ['required', 'array'],
            'features.*.label_hu' => ['nullable', 'string', 'max:80'],
            'features.*.label_en' => ['nullable', 'string', 'max:80'],
            'features.*.credit_cost' => ['required', 'integer', 'min:0', 'max:100000'],
            'features.*.logic' => ['required', 'string', Rule::in([FeatureCatalog::LOGIC_AND, FeatureCatalog::LOGIC_OR])],
            'features.*.package_ids' => ['sometimes', 'array'],
            'features.*.package_ids.*' => ['integer', 'exists:packages,id'],
        ]);

        foreach ($keys as $key) {
            if (! isset($data['features'][$key])) {
                continue;
            }

            $row = $data['features'][$key];
            $rule = FeatureAccess::query()->firstOrCreate(
                ['key' => $key],
                [
                    'credit_cost' => 0,
                    'logic' => FeatureCatalog::LOGIC_AND,
                    'label_hu' => PackageBody::perkLabel($key, 'hu'),
                    'label_en' => PackageBody::perkLabel($key, 'en'),
                ],
            );
            $rule->update([
                'label_hu' => trim((string) ($row['label_hu'] ?? '')) ?: PackageBody::perkLabel($key, 'hu'),
                'label_en' => trim((string) ($row['label_en'] ?? '')) ?: PackageBody::perkLabel($key, 'en'),
                'credit_cost' => (int) $row['credit_cost'],
                'logic' => $row['logic'],
            ]);
            $rule->packages()->sync($row['package_ids'] ?? []);
        }

        $this->features->refresh();
        $this->features->refreshPackageDescriptions();

        return redirect()
            ->route('admin.feature-access.edit')
            ->with('status', __('admin.feature_access.saved'));
    }
}

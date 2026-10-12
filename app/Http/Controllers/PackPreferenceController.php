<?php

namespace App\Http\Controllers;

use App\Models\CardPack;
use App\Models\UserPackPreference;
use App\Services\FeatureAccessService;
use App\Services\PackResolutionService;
use App\Support\FeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackPreferenceController extends Controller
{
    public function update(Request $request, FeatureAccessService $features, PackResolutionService $packs): RedirectResponse
    {
        $user = $request->user();
        $figureIds = CardPack::query()->figure()->active()->pluck('id')->all();
        $backIds = $packs->backOptions()->pluck('id')->all();

        $data = $request->validate([
            'figures' => ['nullable', 'array'],
            'figures.*' => ['nullable', 'integer', Rule::in($figureIds)],
            'card_back' => ['nullable', 'integer', Rule::in($backIds)],
        ]);

        if ($features->can($user, FeatureCatalog::PACKS_CHOOSE)) {
            foreach ($data['figures'] ?? [] as $type => $packId) {
                if (! in_array($type, CardPack::FIGURE_TYPES, true)) {
                    continue;
                }
                if (! $packId) {
                    UserPackPreference::query()
                        ->where('user_id', $user->id)
                        ->where('role', UserPackPreference::ROLE_FIGURE)
                        ->where('pack_type', $type)
                        ->delete();

                    continue;
                }
                $pack = CardPack::query()->active()->figure()->find($packId);
                if ($pack === null || $pack->type !== $type) {
                    continue;
                }
                UserPackPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role' => UserPackPreference::ROLE_FIGURE,
                        'pack_type' => $type,
                    ],
                    [
                        'figure_pack_id' => null,
                        'card_pack_id' => $pack->id,
                    ]
                );
            }
        }

        if ($features->can($user, FeatureCatalog::PACKS_BACKS)) {
            $this->saveCardBack($user, $data['card_back'] ?? null);
        }

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_packs_saved'));
    }

    private function saveCardBack($user, mixed $packId): void
    {
        UserPackPreference::query()
            ->where('user_id', $user->id)
            ->where('role', UserPackPreference::ROLE_CARD_BACK)
            ->delete();

        if (! $packId) {
            return;
        }

        UserPackPreference::query()->create([
            'user_id' => $user->id,
            'role' => UserPackPreference::ROLE_CARD_BACK,
            'figure_pack_id' => null,
            'pack_type' => null,
            'card_pack_id' => (int) $packId,
        ]);
    }
}

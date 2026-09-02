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
        $lightIds = $packs->backgroundOptions(CardPack::POLARITY_LIGHT)->pluck('id')->all();
        $shadowIds = $packs->backgroundOptions(CardPack::POLARITY_SHADOW)->pluck('id')->all();

        $data = $request->validate([
            'figures' => ['nullable', 'array'],
            'figures.*' => ['nullable', 'integer', Rule::in($figureIds)],
            'backs' => ['nullable', 'array'],
            'backs.*' => ['nullable', 'integer', Rule::in(array_merge([0], $backIds))],
            'lights' => ['nullable', 'array'],
            'lights.*' => ['nullable', 'integer', Rule::in(array_merge([0], $lightIds))],
            'shadows' => ['nullable', 'array'],
            'shadows.*' => ['nullable', 'integer', Rule::in(array_merge([0], $shadowIds))],
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
            foreach ($data['backs'] ?? [] as $figureId => $packId) {
                $this->saveSkin($user, (int) $figureId, $packId, UserPackPreference::ROLE_CARD_BACK, $figureIds);
            }
        }

        if ($features->can($user, FeatureCatalog::PACKS_BACKGROUNDS)) {
            foreach ($data['lights'] ?? [] as $figureId => $packId) {
                $this->saveSkin($user, (int) $figureId, $packId, UserPackPreference::ROLE_BACKGROUND_LIGHT, $figureIds);
            }
            foreach ($data['shadows'] ?? [] as $figureId => $packId) {
                $this->saveSkin($user, (int) $figureId, $packId, UserPackPreference::ROLE_BACKGROUND_SHADOW, $figureIds);
            }
        }

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_packs_saved'));
    }

    /**
     * @param  list<int>  $figureIds
     */
    private function saveSkin($user, int $figureId, mixed $packId, string $role, array $figureIds): void
    {
        if (! in_array($figureId, $figureIds, true)) {
            return;
        }
        if (! $packId) {
            UserPackPreference::query()
                ->where('user_id', $user->id)
                ->where('role', $role)
                ->where('figure_pack_id', $figureId)
                ->delete();

            return;
        }
        UserPackPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'role' => $role,
                'figure_pack_id' => $figureId,
            ],
            [
                'pack_type' => null,
                'card_pack_id' => (int) $packId,
            ]
        );
    }
}

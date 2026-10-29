<?php

namespace App\Http\Controllers;

use App\Models\CardPack;
use App\Models\DataDeletionRequest;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserPackPreference;
use Carbon\Carbon;
use App\Services\FeatureAccessService;
use App\Services\PackResolutionService;
use App\Services\PersonalDataService;
use App\Services\PlaceGeocoder;
use App\Support\CountryCatalog;
use App\Support\FeatureCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
    public function __construct(
        private readonly PersonalDataService $personalData,
        private readonly FeatureAccessService $features,
        private readonly PackResolutionService $packs,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        $readings = $user->readingLogs()
            ->latest('id')
            ->paginate(20);

        $creditRequests = $user->creditRequests()
            ->latest('id')
            ->limit(50)
            ->get();

        $pendingDeletion = $user->dataDeletionRequests()
            ->where('status', DataDeletionRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        $prefs = $user->packPreferences()->get();
        $figurePrefs = $prefs
            ->where('role', UserPackPreference::ROLE_FIGURE)
            ->keyBy('pack_type');

        $figureOptions = [];
        foreach (CardPack::FIGURE_TYPES as $type) {
            $figureOptions[$type] = $this->packs->figureOptions($type);
        }

        return view('account.show', [
            'user' => $user,
            'readings' => $readings,
            'creditRequests' => $creditRequests,
            'pendingDeletion' => $pendingDeletion,
            'assetBase' => asset('templates/art-deco'),
            'figureOptions' => $figureOptions,
            'backOptions' => $this->packs->backOptions(),
            'selectedBack' => $this->packs->chosenCardBack($user),
            'figurePrefs' => $figurePrefs,
            'canChoosePacks' => $this->features->can($user, FeatureCatalog::PACKS_CHOOSE),
            'canChooseBacks' => $this->features->can($user, FeatureCatalog::PACKS_BACKS),
        ]);
    }

    public function showReading(Request $request, ReadingLog $reading): View
    {
        abort_unless((int) $reading->user_id === (int) $request->user()->id, 404);

        return view('account.reading', [
            'log' => $reading,
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function destroyReading(Request $request, ReadingLog $reading): RedirectResponse
    {
        abort_unless((int) $reading->user_id === (int) $request->user()->id, 404);

        $reading->delete();

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_deleted'));
    }

    public function destroyAllReadings(Request $request): RedirectResponse
    {
        $this->personalData->eraseConversations($request->user());

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_deleted_all'));
    }

    public function storeDeletionRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->personalData->requestErasure($request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            $key = $e->getMessage() === 'pending_exists'
                ? 'account_erasure_pending'
                : 'account_erasure_unavailable';

            return back()->withErrors(['erasure' => __("messages.{$key}")]);
        }

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_erasure_sent'));
    }

    public function introduction(Request $request): View
    {
        $user = $request->user();
        $birthCountry = old('birth_country', $user->birth_country);
        $residenceCountry = old('residence_country', $user->residence_country);

        return view('account.introduction', [
            'user' => $user,
            'assetBase' => asset('templates/art-deco'),
            'countries' => CountryCatalog::all(),
            'birthCountry' => CountryCatalog::nameFor($birthCountry) ?? $birthCountry,
            'birthCountryCode' => CountryCatalog::codeFor($birthCountry),
            'residenceCountry' => CountryCatalog::nameFor($residenceCountry) ?? $residenceCountry,
            'residenceCountryCode' => CountryCatalog::codeFor($residenceCountry),
        ]);
    }

    public function cities(Request $request, PlaceGeocoder $places): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        if (! CountryCatalog::known($data['country'])) {
            return response()->json(['cities' => []]);
        }

        return response()->json([
            'cities' => $places->cities($data['country'], $data['q']),
        ]);
    }

    public function updateIntroduction(Request $request, PlaceGeocoder $places): RedirectResponse
    {
        $data = $request->validate([
            'birth_at' => ['nullable', 'date'],
            'birth_country' => ['nullable', 'string', 'max:80'],
            'birth_city' => ['nullable', 'string', 'max:80'],
            'residence_country' => ['nullable', 'string', 'max:80'],
            'residence_city' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'in:female,male,other'],
            'introduction' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $birth = filled($data['birth_at'] ?? null)
            ? Carbon::parse($data['birth_at'], 'Europe/Budapest')->utc()
            : null;

        $user->forceFill([
            'birth_at' => $birth,
            ...$this->resolvedPlace($places, $user, 'birth', $data['birth_country'] ?? null, $data['birth_city'] ?? null),
            ...$this->resolvedPlace($places, $user, 'residence', $data['residence_country'] ?? null, $data['residence_city'] ?? null),
            'gender' => $data['gender'] ?? null,
            'introduction' => trim((string) ($data['introduction'] ?? '')) ?: null,
        ])->save();

        return redirect()
            ->route('account.introduction')
            ->with('status', __('messages.intro_saved'));
    }

    /**
     * @return array<string, string|float|null>
     */
    private function resolvedPlace(PlaceGeocoder $places, User $user, string $prefix, ?string $country, ?string $city): array
    {
        $country = $this->blankPlace($country);
        $city = $this->blankPlace($city);
        if ($country === null && $city === null) {
            return [
                $prefix.'_country' => null,
                $prefix.'_city' => null,
                $prefix.'_latitude' => null,
                $prefix.'_longitude' => null,
            ];
        }
        if ($country === null || $city === null) {
            throw ValidationException::withMessages([
                $prefix.'_city' => __('messages.intro_place_pair'),
            ]);
        }

        $same = mb_strtolower($country) === mb_strtolower((string) $user->{$prefix.'_country'})
            && mb_strtolower($city) === mb_strtolower((string) $user->{$prefix.'_city'})
            && $user->{$prefix.'_latitude'} !== null
            && $user->{$prefix.'_longitude'} !== null;
        if ($same) {
            return [
                $prefix.'_country' => $country,
                $prefix.'_city' => $city,
                $prefix.'_latitude' => $user->{$prefix.'_latitude'},
                $prefix.'_longitude' => $user->{$prefix.'_longitude'},
            ];
        }

        $coords = $places->coordinates($country, $city);
        if ($coords === null) {
            throw ValidationException::withMessages([
                $prefix.'_city' => __('messages.intro_place_unknown'),
            ]);
        }

        return [
            $prefix.'_country' => $country,
            $prefix.'_city' => $city,
            $prefix.'_latitude' => $coords['latitude'],
            $prefix.'_longitude' => $coords['longitude'],
        ];
    }

    private function blankPlace(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->usesPasswordLogin(), 403);

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => __('messages.account_password_current_invalid'),
        ]);

        $user->password = $data['password'];
        $user->save();

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_password_updated'));
    }
}

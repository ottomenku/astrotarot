<?php

namespace App\Http\Controllers;

use App\Models\CreditRequest;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Support\FeatureCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CreditPackageController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly FeatureAccessService $features,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return view('credits.packages', [
            'creditPacks' => $this->credits->packages(),
            'plans' => $this->credits->plans(),
            'currentPlan' => $user?->planKey() ?? CreditService::PLAN_FREE,
            'balance' => (int) ($user?->credits ?? 0),
            'locale' => $locale,
            'assetBase' => asset('templates/art-deco'),
            'astroMottoUrl' => CreditService::ASTROMOTTO_URL,
            'shareUrl' => route('promo.share'),
            'canViewHistory' => $user ? $this->credits->canViewHistory($user) : false,
            'hasAstroMotto' => $user ? $this->credits->hasAstroMottoAccess($user) : false,
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'package_key' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $package = $this->credits->package($data['package_key']);
        if ($package === null) {
            return back()->withErrors(['package_key' => __('messages.credits_package_invalid')]);
        }

        $decision = $this->features->decide($request->user(), FeatureCatalog::CREDITS_REQUEST);
        if ($decision['will_charge']) {
            try {
                $this->credits->spend($request->user(), $decision['cost']);
            } catch (RuntimeException $e) {
                return back()->withErrors(['package_key' => $e->getMessage()]);
            }
        }

        CreditRequest::query()->create([
            'user_id' => $request->user()->id,
            'package_key' => $package['key'],
            'credits' => $package['credits'],
            'status' => 'pending',
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('messages.credits_request_sent'));
    }

    public function activatePromo(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'max:64', 'alpha_dash'],
        ]);

        try {
            $user = $this->credits->activatePromoPlan($request->user(), $data['plan']);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['plan' => $e->getMessage()]);
        }

        $plan = $this->credits->plan($user->plan);
        $message = __('messages.plan_promo_activated', [
            'plan' => $plan?->name() ?? $data['plan'],
            'credits' => (int) ($plan?->credits ?? 0),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'plan' => $user->plan,
                'credits' => (int) $user->credits,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('credits.packages')
            ->with('status', $message);
    }
}

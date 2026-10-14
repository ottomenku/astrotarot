<?php

namespace App\Http\Controllers;

use App\Models\CardPack;
use App\Models\Template;
use App\Services\AppSettings;
use App\Services\CardDrawProfileService;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\GameConfigService;
use App\Support\CardPackCatalog;
use App\Support\FeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, CardDrawProfileService $drawProfile, AppSettings $settings, GameConfigService $games): View|RedirectResponse
    {
        if ($request->boolean('spread')) {
            session(['spread' => [
                'game' => $request->integer('game') ?: null,
                'packs' => array_filter([
                    CardPack::TYPE_ORACLE => trim((string) $request->input('pack_oracle', '')),
                    CardPack::TYPE_TAROT => trim((string) $request->input('pack_tarot', '')),
                ], fn (string $slug): bool => $slug !== ''),
                'back' => $request->integer('back') ?: null,
            ]]);

            return redirect()->route('home', $request->user() ? [] : ['draw' => 1]);
        }

        $selection = session('spread', []);
        if (! is_array($selection)) {
            $selection = [];
        }

        $template = Template::query()
            ->where('is_active', true)
            ->first();

        $landingView = $template ? "templates.{$template->slug}.landing" : null;
        if (! auth()->check() && $landingView && view()->exists($landingView) && ! request()->boolean('draw')) {
            return view($landingView, [
                'template' => $template,
                'assetBase' => asset("templates/{$template->slug}"),
                'registrationEnabled' => $settings->registrationEnabled(),
            ]);
        }

        if ($template && view()->exists("templates.{$template->slug}.home")) {
            $user = auth()->user();
            if ($user?->isAdmin()) {
                app(CreditService::class)->grantDailyLoginBonus($user);
                $user->refresh();
            }
            $snapshot = app(FeatureAccessService::class)->snapshot($user);

            return view("templates.{$template->slug}.home", [
                'template' => $template,
                'assetBase' => asset("templates/{$template->slug}"),
                'drawProfile' => $drawProfile->forNow(),
                'gameConfig' => $games->homepage(app()->getLocale(), $selection),
                'packBase' => asset(CardPackCatalog::publicPrefix()),
                'minQuestionChars' => $settings->minQuestionChars(),
                'llmEnabled' => $settings->llmEnabled(),
                'registrationEnabled' => $settings->registrationEnabled(),
                'featureAccess' => $snapshot,
                'canOpenCredits' => $settings->paidFeaturesEnabled()
                    && (bool) ($snapshot[FeatureCatalog::CREDITS_PAGE]['allowed'] ?? false),
                'canOpenAccount' => (bool) ($snapshot[FeatureCatalog::ACCOUNT]['allowed'] ?? false),
                'canViewHistory' => (bool) ($snapshot[FeatureCatalog::HISTORY]['allowed'] ?? false),
            ]);
        }

        return view('welcome');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\AppSettings;
use App\Services\CardDrawProfileService;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\GameConfigService;
use App\Support\CardPackCatalog;
use App\Support\FeatureCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(CardDrawProfileService $drawProfile, AppSettings $settings, GameConfigService $games): View
    {
        $template = Template::query()
            ->where('is_active', true)
            ->first();

        $landingView = $template ? "templates.{$template->slug}.landing" : null;
        if (! auth()->check() && $landingView && view()->exists($landingView)) {
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
                'gameConfig' => $games->homepage(app()->getLocale()),
                'packBase' => asset(CardPackCatalog::publicPrefix()),
                'minQuestionChars' => $settings->minQuestionChars(),
                'llmEnabled' => $settings->llmEnabled(),
                'registrationEnabled' => $settings->registrationEnabled(),
                'featureAccess' => $snapshot,
                'canOpenCredits' => (bool) ($snapshot[FeatureCatalog::CREDITS_PAGE]['allowed'] ?? false),
                'canOpenAccount' => (bool) ($snapshot[FeatureCatalog::ACCOUNT]['allowed'] ?? false),
                'canViewHistory' => (bool) ($snapshot[FeatureCatalog::HISTORY]['allowed'] ?? false),
            ]);
        }

        return view('welcome');
    }
}

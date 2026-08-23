<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\AppSettings;
use App\Services\CardDrawProfileService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(CardDrawProfileService $drawProfile, AppSettings $settings): View
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
            return view("templates.{$template->slug}.home", [
                'template' => $template,
                'assetBase' => asset("templates/{$template->slug}"),
                'drawProfile' => $drawProfile->forNow(),
                'minQuestionChars' => $settings->minQuestionChars(),
                'llmEnabled' => $settings->llmEnabled(),
                'registrationEnabled' => $settings->registrationEnabled(),
            ]);
        }

        return view('welcome');
    }
}

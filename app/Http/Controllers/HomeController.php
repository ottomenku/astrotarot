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

        if ($template && view()->exists("templates.{$template->slug}.home")) {
            return view("templates.{$template->slug}.home", [
                'template' => $template,
                'assetBase' => asset("templates/{$template->slug}"),
                'drawProfile' => $drawProfile->forNow(),
                'minQuestionChars' => $settings->minQuestionChars(),
                'llmEnabled' => $settings->llmEnabled(),
            ]);
        }

        return view('welcome');
    }
}

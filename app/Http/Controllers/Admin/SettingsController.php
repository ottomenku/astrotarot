<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(AppSettings $settings): View
    {
        return view('admin.settings.edit', [
            'minQuestionChars' => $settings->minQuestionChars(),
            'siteEnabled' => $settings->siteEnabled(),
            'registrationEnabled' => $settings->registrationEnabled(),
            'llmEnabled' => $settings->llmEnabled(),
            'llmRoute' => $settings->effectiveLlmRoute(),
            'paidFeaturesEnabled' => $settings->paidFeaturesEnabled(),
            'smallModel' => (string) config('services.openai.ollama_small_model', 'llama3.2'),
            'largeModel' => (string) config('services.openai.ollama_large_model', 'qwen2.5:14b'),
            'cloudModel' => (string) config('services.openai.cloud_model', 'gpt-4o-mini'),
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'min_question_chars' => ['required', 'integer', 'min:1', 'max:280'],
            'site_enabled' => ['nullable', 'boolean'],
            'registration_enabled' => ['nullable', 'boolean'],
            'llm_enabled' => ['nullable', 'boolean'],
            'paid_features_enabled' => ['nullable', 'boolean'],
            'llm_route' => ['required', 'string', Rule::in(AppSettings::LLM_ROUTES)],
        ]);

        $settings->putMany([
            AppSettings::MIN_QUESTION_CHARS => (string) $data['min_question_chars'],
            AppSettings::SITE_ENABLED => $request->boolean('site_enabled') ? '1' : '0',
            AppSettings::REGISTRATION_ENABLED => $request->boolean('registration_enabled') ? '1' : '0',
            AppSettings::LLM_ENABLED => $request->boolean('llm_enabled') ? '1' : '0',
            AppSettings::PAID_FEATURES_ENABLED => $request->boolean('paid_features_enabled') ? '1' : '0',
            AppSettings::LLM_ROUTE => $data['llm_route'],
        ]);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', __('admin.settings.saved'));
    }
}

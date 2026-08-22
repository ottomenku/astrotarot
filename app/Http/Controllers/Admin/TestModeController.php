<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestModeController extends Controller
{
    public function edit(AppSettings $settings): View
    {
        return view('admin.test-mode.edit', [
            'siteEnabled' => $settings->siteEnabled(),
            'llmEnabled' => $settings->llmEnabled(),
            'llmRoute' => $settings->effectiveLlmRoute(),
            'smallModel' => (string) config('services.openai.ollama_small_model', 'llama3.2'),
            'largeModel' => (string) config('services.openai.ollama_large_model', 'qwen2.5:14b'),
            'cloudModel' => (string) config('services.openai.cloud_model', 'gpt-4o-mini'),
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'site_enabled' => ['nullable', 'boolean'],
            'llm_enabled' => ['nullable', 'boolean'],
            'llm_route' => ['required', 'string', Rule::in(AppSettings::LLM_ROUTES)],
        ]);

        $settings->putMany([
            AppSettings::SITE_ENABLED => $request->boolean('site_enabled') ? '1' : '0',
            AppSettings::LLM_ENABLED => $request->boolean('llm_enabled') ? '1' : '0',
            AppSettings::LLM_ROUTE => $data['llm_route'],
        ]);

        return redirect()
            ->route('admin.test-mode.edit')
            ->with('status', __('admin.test_mode.saved'));
    }
}

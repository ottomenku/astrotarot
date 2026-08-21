<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromptController extends Controller
{
    public function edit(AppSettings $settings): View
    {
        return view('admin.prompts.edit', [
            'systemPromptHu' => $settings->systemPrompt('hu'),
            'systemPromptEn' => $settings->systemPrompt('en'),
            'clarifyPromptHu' => $settings->clarifyPrompt('hu'),
            'clarifyPromptEn' => $settings->clarifyPrompt('en'),
            'expandPromptHu' => $settings->expandPrompt('hu'),
            'expandPromptEn' => $settings->expandPrompt('en'),
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'system_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'system_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
            'clarify_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'clarify_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
            'expand_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'expand_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
        ]);

        $settings->putMany([
            AppSettings::SYSTEM_PROMPT_HU => $data['system_prompt_hu'],
            AppSettings::SYSTEM_PROMPT_EN => $data['system_prompt_en'],
            AppSettings::CLARIFY_PROMPT_HU => $data['clarify_prompt_hu'],
            AppSettings::CLARIFY_PROMPT_EN => $data['clarify_prompt_en'],
            AppSettings::EXPAND_PROMPT_HU => $data['expand_prompt_hu'],
            AppSettings::EXPAND_PROMPT_EN => $data['expand_prompt_en'],
        ]);

        return redirect()
            ->route('admin.prompts.edit')
            ->with('status', __('admin.prompts.saved'));
    }

    public function reset(AppSettings $settings): RedirectResponse
    {
        $settings->putMany([
            AppSettings::SYSTEM_PROMPT_HU => $settings->defaultSystemPromptHu(),
            AppSettings::SYSTEM_PROMPT_EN => $settings->defaultSystemPromptEn(),
            AppSettings::CLARIFY_PROMPT_HU => $settings->defaultClarifyPromptHu(),
            AppSettings::CLARIFY_PROMPT_EN => $settings->defaultClarifyPromptEn(),
            AppSettings::EXPAND_PROMPT_HU => $settings->defaultExpandPromptHu(),
            AppSettings::EXPAND_PROMPT_EN => $settings->defaultExpandPromptEn(),
        ]);

        return redirect()
            ->route('admin.prompts.edit')
            ->with('status', __('admin.prompts.reset'));
    }
}

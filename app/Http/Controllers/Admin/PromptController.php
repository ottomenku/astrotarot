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
            'basePromptHu' => $settings->basePrompt('hu'),
            'basePromptEn' => $settings->basePrompt('en'),
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'system_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'system_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
            'base_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'base_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
        ]);

        $settings->putMany([
            AppSettings::SYSTEM_PROMPT_HU => $data['system_prompt_hu'],
            AppSettings::SYSTEM_PROMPT_EN => $data['system_prompt_en'],
            AppSettings::BASE_PROMPT_HU => $data['base_prompt_hu'],
            AppSettings::BASE_PROMPT_EN => $data['base_prompt_en'],
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
            AppSettings::BASE_PROMPT_HU => $settings->defaultBasePromptHu(),
            AppSettings::BASE_PROMPT_EN => $settings->defaultBasePromptEn(),
        ]);

        return redirect()
            ->route('admin.prompts.edit')
            ->with('status', __('admin.prompts.reset'));
    }
}

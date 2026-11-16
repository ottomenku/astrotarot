<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\AppSettings;
use App\Services\OpenAIReadingAgent;
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
            'classifyPromptHu' => $settings->classifyPrompt('hu'),
            'classifyPromptEn' => $settings->classifyPrompt('en'),
            'categoryAnswersHu' => $settings->categoryAnswers('hu'),
            'categoryAnswersEn' => $settings->categoryAnswers('en'),
            'categoryCodes' => Game::SPREAD_TYPES,
        ]);
    }

    public function update(Request $request, AppSettings $settings, OpenAIReadingAgent $agents): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $settings->putMany([
            AppSettings::SYSTEM_PROMPT_HU => $data['system_prompt_hu'],
            AppSettings::SYSTEM_PROMPT_EN => $data['system_prompt_en'],
            AppSettings::BASE_PROMPT_HU => $data['base_prompt_hu'],
            AppSettings::BASE_PROMPT_EN => $data['base_prompt_en'],
            AppSettings::CLASSIFY_PROMPT_HU => $data['classify_prompt_hu'],
            AppSettings::CLASSIFY_PROMPT_EN => $data['classify_prompt_en'],
            AppSettings::CATEGORY_ANSWERS_HU => $this->encodeAnswers($data['category_answers_hu']),
            AppSettings::CATEGORY_ANSWERS_EN => $this->encodeAnswers($data['category_answers_en']),
        ]);

        return redirect()
            ->route('admin.prompts.edit')
            ->with('status', __('admin.prompts.saved').$this->agentNote($agents->sync()));
    }

    public function reset(AppSettings $settings, OpenAIReadingAgent $agents): RedirectResponse
    {
        $settings->putMany([
            AppSettings::SYSTEM_PROMPT_HU => $settings->defaultSystemPromptHu(),
            AppSettings::SYSTEM_PROMPT_EN => $settings->defaultSystemPromptEn(),
            AppSettings::BASE_PROMPT_HU => $settings->defaultBasePromptHu(),
            AppSettings::BASE_PROMPT_EN => $settings->defaultBasePromptEn(),
            AppSettings::CLASSIFY_PROMPT_HU => $settings->defaultClassifyPromptHu(),
            AppSettings::CLASSIFY_PROMPT_EN => $settings->defaultClassifyPromptEn(),
            AppSettings::CATEGORY_ANSWERS_HU => $this->encodeAnswers($settings->defaultCategoryAnswersHu()),
            AppSettings::CATEGORY_ANSWERS_EN => $this->encodeAnswers($settings->defaultCategoryAnswersEn()),
        ]);

        return redirect()
            ->route('admin.prompts.edit')
            ->with('status', __('admin.prompts.reset').$this->agentNote($agents->sync()));
    }

    private function agentNote(?bool $synced): string
    {
        return $synced === false ? ' '.__('admin.prompts.agent_failed') : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'system_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'system_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
            'base_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'base_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
            'classify_prompt_hu' => ['required', 'string', 'min:20', 'max:20000'],
            'classify_prompt_en' => ['required', 'string', 'min:20', 'max:20000'],
            'category_answers_hu' => ['required', 'array'],
            'category_answers_en' => ['required', 'array'],
            'category_answers_hu.shape' => ['required', 'string', 'min:20', 'max:4000'],
            'category_answers_en.shape' => ['required', 'string', 'min:20', 'max:4000'],
        ];
        foreach (Game::SPREAD_TYPES as $code) {
            $rules['category_answers_hu.'.$code] = ['required', 'string', 'min:10', 'max:4000'];
            $rules['category_answers_en.'.$code] = ['required', 'string', 'min:10', 'max:4000'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function encodeAnswers(array $answers): string
    {
        $encoded = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return is_string($encoded) ? $encoded : '{}';
    }
}

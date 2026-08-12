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
            'drawMode' => $settings->drawMode(),
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'min_question_chars' => ['required', 'integer', 'min:1', 'max:280'],
            'draw_mode' => [
                'required',
                'string',
                Rule::in([
                    AppSettings::DRAW_MODE_SCORING,
                    AppSettings::DRAW_MODE_RANDOM_POLARITY,
                ]),
            ],
        ]);

        $settings->putMany([
            AppSettings::MIN_QUESTION_CHARS => (string) $data['min_question_chars'],
            AppSettings::DRAW_MODE => $data['draw_mode'],
        ]);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', __('admin.settings.saved'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function edit(AppSettings $settings): View
    {
        return view('admin.legal.edit', [
            'termsHu' => $settings->terms('hu'),
            'termsEn' => $settings->terms('en'),
            'privacyHu' => $settings->privacy('hu'),
            'privacyEn' => $settings->privacy('en'),
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'terms_hu' => ['required', 'string', 'min:20', 'max:50000'],
            'terms_en' => ['required', 'string', 'min:20', 'max:50000'],
            'privacy_hu' => ['required', 'string', 'min:20', 'max:50000'],
            'privacy_en' => ['required', 'string', 'min:20', 'max:50000'],
        ]);

        $settings->putMany([
            AppSettings::TERMS_HU => $data['terms_hu'],
            AppSettings::TERMS_EN => $data['terms_en'],
            AppSettings::PRIVACY_HU => $data['privacy_hu'],
            AppSettings::PRIVACY_EN => $data['privacy_en'],
        ]);

        return redirect()
            ->route('admin.legal.edit')
            ->with('status', __('admin.legal.saved'));
    }
}

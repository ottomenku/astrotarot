<?php

namespace App\Http\Controllers;

use App\Services\AppSettings;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function terms(AppSettings $settings): View
    {
        return view('legal.show', [
            'title' => __('messages.legal_terms'),
            'body' => $settings->terms(app()->getLocale()),
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function privacy(AppSettings $settings): View
    {
        return view('legal.show', [
            'title' => __('messages.legal_privacy'),
            'body' => $settings->privacy(app()->getLocale()),
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function contact(): View
    {
        return view('legal.contact', [
            'email' => (string) config('app.contact_email'),
            'assetBase' => asset('templates/art-deco'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse|Response
    {
        if (! in_array($locale, ['hu', 'en'], true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->noContent();
        }

        return back();
    }
}

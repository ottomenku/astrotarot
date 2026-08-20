<?php

namespace App\Http\Controllers;

use App\Models\CreditRequest;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditPackageController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('credits.packages', [
            'packages' => $this->credits->packages(),
            'balance' => (int) ($user?->credits ?? 0),
            'locale' => app()->getLocale(),
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'package_key' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $package = $this->credits->package($data['package_key']);
        if ($package === null) {
            return back()->withErrors(['package_key' => __('messages.credits_package_invalid')]);
        }

        CreditRequest::query()->create([
            'user_id' => $request->user()->id,
            'package_key' => $package['key'],
            'credits' => $package['credits'],
            'status' => 'pending',
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('messages.credits_request_sent'));
    }
}

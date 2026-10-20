<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardPack;
use App\Services\PackInstallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PackInstallController extends Controller
{
    public function create(): View
    {
        return view('admin.packs.install');
    }

    public function store(Request $request, PackInstallService $installer): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('card_packs', 'slug')],
            'card_count' => ['required', 'integer', 'min:1', 'max:500'],
            'name_hu' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'description_hu' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(CardPack::TYPES)],
            'archive' => ['required', 'file', 'extensions:zip', 'max:65536'],
        ]);

        $pack = $installer->install([
            'slug' => $data['slug'],
            'card_count' => (int) $data['card_count'],
            'name_hu' => $data['name_hu'],
            'name_en' => $data['name_en'],
            'description_hu' => (string) ($data['description_hu'] ?? ''),
            'description_en' => (string) ($data['description_en'] ?? ''),
            'type' => $data['type'],
        ], $request->file('archive'));

        return redirect()
            ->route('admin.packs.edit', $pack)
            ->with('status', __('admin.pack_install.installed', ['count' => $pack->cards_count]));
    }
}

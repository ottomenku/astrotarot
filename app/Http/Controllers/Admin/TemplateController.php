<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.templates.index', [
            'templates' => Template::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('templates', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $slug = $validated['slug'] ?: Str::slug($validated['name']);

        Template::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_active' => Template::query()->count() === 0,
        ]);

        return redirect()
            ->route('admin.templates.index')
            ->with('status', __('admin.templates.saved'));
    }

    public function activate(Template $template): RedirectResponse
    {
        DB::transaction(function () use ($template) {
            Template::query()->where('is_active', true)->update(['is_active' => false]);
            $template->update(['is_active' => true]);
        });

        return redirect()
            ->route('admin.templates.index')
            ->with('status', __('admin.templates.activated'));
    }
}

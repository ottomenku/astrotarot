<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SlotAllocationController extends Controller
{
    public function edit(AppSettings $settings): View
    {
        return view('admin.slots.edit', [
            'allocation' => $settings->slotAllocation(),
            'sources' => AppSettings::SLOT_SOURCES,
        ]);
    }

    public function update(Request $request, AppSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'slots' => ['required', 'array', 'size:3'],
            'slots.*' => ['required', 'string', Rule::in(AppSettings::SLOT_SOURCES)],
        ]);

        $allocation = array_values($data['slots']);

        $settings->putMany([
            AppSettings::SLOT_ALLOCATION => json_encode($allocation, JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()
            ->route('admin.slots.edit')
            ->with('status', __('admin.slots.saved'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReadingLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ReadingLog::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('question', 'like', $term)
                        ->orWhere('response', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.readings.index', [
            'logs' => $logs,
        ]);
    }

    public function show(ReadingLog $reading): View
    {
        return view('admin.readings.show', [
            'log' => $reading,
        ]);
    }
}

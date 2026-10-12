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
            ->with('user')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('question', 'like', $term)
                        ->orWhere('response', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.readings.index', [
            'logs' => $logs,
        ]);
    }

    public function preview(ReadingLog $reading): View
    {
        $reading->loadMissing('user');

        return view('admin.readings.preview', [
            'log' => $reading,
        ]);
    }

    public function show(ReadingLog $reading): View
    {
        $reading->loadMissing('user');

        return view('admin.readings.show', [
            'log' => $reading,
        ]);
    }
}

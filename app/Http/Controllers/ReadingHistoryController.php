<?php

namespace App\Http\Controllers;

use App\Models\ReadingLog;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingHistoryController extends Controller
{
    public function __construct(private readonly CreditService $credits)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->credits->canViewHistory($user), 403);

        $logs = ReadingLog::query()
            ->where('user_id', $user->id)
            ->where('status', 'success')
            ->latest('id')
            ->paginate(20);

        return view('credits.history', [
            'logs' => $logs,
            'assetBase' => asset('templates/art-deco'),
            'locale' => app()->getLocale(),
        ]);
    }

    public function show(Request $request, ReadingLog $reading): View
    {
        $user = $request->user();
        abort_unless($this->credits->canViewHistory($user), 403);
        abort_unless((int) $reading->user_id === (int) $user->id, 404);

        return view('credits.history-show', [
            'log' => $reading,
            'assetBase' => asset('templates/art-deco'),
            'locale' => app()->getLocale(),
        ]);
    }
}

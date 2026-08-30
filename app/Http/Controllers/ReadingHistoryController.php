<?php

namespace App\Http\Controllers;

use App\Models\ReadingLog;
use App\Services\FeatureAccessService;
use App\Support\FeatureCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingHistoryController extends Controller
{
    public function __construct(private readonly FeatureAccessService $features) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->features->abortUnlessCan($user, FeatureCatalog::HISTORY);

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
        $this->features->abortUnlessCan($user, FeatureCatalog::HISTORY);
        abort_unless((int) $reading->user_id === (int) $user->id, 404);
        abort_unless((int) $reading->user_id === (int) $user->id, 404);

        return view('credits.history-show', [
            'log' => $reading,
            'assetBase' => asset('templates/art-deco'),
            'locale' => app()->getLocale(),
        ]);
    }
}

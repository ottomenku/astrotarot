<?php

namespace App\Http\Controllers;

use App\Models\DataDeletionRequest;
use App\Models\ReadingLog;
use App\Services\PersonalDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
    public function __construct(private readonly PersonalDataService $personalData) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        $readings = $user->readingLogs()
            ->latest('id')
            ->paginate(20);

        $creditRequests = $user->creditRequests()
            ->latest('id')
            ->limit(50)
            ->get();

        $pendingDeletion = $user->dataDeletionRequests()
            ->where('status', DataDeletionRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        return view('account.show', [
            'user' => $user,
            'readings' => $readings,
            'creditRequests' => $creditRequests,
            'pendingDeletion' => $pendingDeletion,
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function showReading(Request $request, ReadingLog $reading): View
    {
        abort_unless((int) $reading->user_id === (int) $request->user()->id, 404);

        return view('account.reading', [
            'log' => $reading,
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function destroyReading(Request $request, ReadingLog $reading): RedirectResponse
    {
        abort_unless((int) $reading->user_id === (int) $request->user()->id, 404);

        $reading->delete();

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_deleted'));
    }

    public function destroyAllReadings(Request $request): RedirectResponse
    {
        $this->personalData->eraseConversations($request->user());

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_deleted_all'));
    }

    public function storeDeletionRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->personalData->requestErasure($request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            $key = $e->getMessage() === 'pending_exists'
                ? 'account_erasure_pending'
                : 'account_erasure_unavailable';

            return back()->withErrors(['erasure' => __("messages.{$key}")]);
        }

        return redirect()
            ->route('account.show')
            ->with('status', __('messages.account_erasure_sent'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataDeletionRequest;
use App\Services\PersonalDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DataDeletionRequestController extends Controller
{
    public function __construct(private readonly PersonalDataService $personalData) {}

    public function index(): View
    {
        $requests = DataDeletionRequest::query()
            ->with(['user', 'processedBy'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('id')
            ->paginate(20);

        return view('admin.deletion-requests.index', [
            'requests' => $requests,
        ]);
    }

    public function process(Request $httpRequest, DataDeletionRequest $deletionRequest): RedirectResponse
    {
        $targetId = (int) $deletionRequest->user_id;

        try {
            $this->personalData->processErasure($deletionRequest, $httpRequest->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => __('admin.deletion_requests.not_pending')]);
        }

        if ($httpRequest->user()->id === $targetId) {
            auth()->logout();
            $httpRequest->session()->invalidate();
            $httpRequest->session()->regenerateToken();

            return redirect()
                ->route('home')
                ->with('status', __('admin.deletion_requests.processed'));
        }

        return redirect()
            ->route('admin.deletion-requests.index')
            ->with('status', __('admin.deletion_requests.processed'));
    }

    public function reject(Request $httpRequest, DataDeletionRequest $deletionRequest): RedirectResponse
    {
        try {
            $this->personalData->rejectErasure($deletionRequest, $httpRequest->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => __('admin.deletion_requests.not_pending')]);
        }

        return redirect()
            ->route('admin.deletion-requests.index')
            ->with('status', __('admin.deletion_requests.rejected'));
    }
}

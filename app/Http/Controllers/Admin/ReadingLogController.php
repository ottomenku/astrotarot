<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmExchange;
use App\Models\ReadingLog;
use App\Models\ReadingSession;
use App\Services\ReadingCostEstimate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ReadingLog::query()
            ->with(['user', 'readingSession'])
            ->adminRoots()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('question', 'like', $term)
                        ->orWhere('response', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        })
                        ->orWhereIn('session_public_id', function ($sub) use ($term) {
                            $sub->select('session_public_id')
                                ->from('reading_logs')
                                ->whereNotNull('session_public_id')
                                ->where('session_public_id', '!=', '')
                                ->where(function ($match) use ($term) {
                                    $match->where('question', 'like', $term)
                                        ->orWhere('response', 'like', $term);
                                });
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
        return view('admin.readings.preview', $this->threadData($reading));
    }

    public function show(ReadingLog $reading): View
    {
        $data = $this->threadData($reading);
        $data['cost'] = app(ReadingCostEstimate::class)->forLogs($data['thread']);
        $data['exchanges'] = $this->exchanges($data['session'], $data['thread']);

        return view('admin.readings.show', $data);
    }

    /**
     * @return array{log: ReadingLog, thread: \Illuminate\Support\Collection<int, ReadingLog>, session: ?ReadingSession}
     */
    private function threadData(ReadingLog $reading): array
    {
        $thread = $reading->thread();
        $root = $thread->first() ?? $reading;
        $root->loadMissing('user');
        $session = filled($root->session_public_id)
            ? ReadingSession::query()->where('public_id', $root->session_public_id)->first()
            : null;

        return [
            'log' => $root,
            'thread' => $thread,
            'session' => $session,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ReadingLog>  $thread
     * @return \Illuminate\Support\Collection<int, LlmExchange>
     */
    private function exchanges(?ReadingSession $session, $thread)
    {
        if ($session !== null) {
            return $session->exchanges()->orderBy('id')->get();
        }

        return LlmExchange::query()
            ->whereIn('reading_log_id', $thread->pluck('id'))
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ReadingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReadingFeedbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'verdict' => ['nullable', 'string', Rule::in([
                'useful',
                'too_spiritual',
                'too_complex',
                'too_general',
                'useless',
            ])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $comment = trim((string) ($data['comment'] ?? ''));
        $rating = $data['rating'] ?? null;
        $verdict = $data['verdict'] ?? null;
        if ($rating === null && $verdict === null && $comment === '') {
            return response()->json([
                'error' => __('messages.feedback_need'),
            ], 422);
        }

        $session = ReadingSession::query()->where('public_id', $data['session_id'])->first();
        if ($session === null) {
            return response()->json([
                'error' => __('messages.feedback_missing'),
            ], 404);
        }

        $session->update([
            'feedback_rating' => $rating,
            'feedback_verdict' => $verdict,
            'feedback_comment' => $comment !== '' ? $comment : null,
        ]);

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\AppSettings;
use App\Services\CreditService;
use App\Services\TarotReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ReadingController extends Controller
{
    public function __invoke(
        Request $request,
        TarotReadingService $readings,
        AppSettings $settings,
        CreditService $credits,
    ): JsonResponse {
        set_time_limit(max(180, (int) config('services.openai.timeout', 120) + 30));

        if (! $settings->llmEnabled()) {
            return response()->json([
                'error' => __('messages.llm_disabled'),
            ], 503);
        }

        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'error' => __('messages.credits_auth_required'),
            ], 401);
        }

        $minChars = $settings->minQuestionChars();

        $data = $request->validate([
            'question' => ['required', 'string', 'min:'.$minChars, 'max:280'],
            'locale' => ['nullable', 'string', 'in:hu,en'],
            'mode' => ['nullable', 'string', 'in:initial,clarify,expand'],
            'previous_reading' => ['nullable', 'string', 'max:12000'],
            'follow_up' => ['nullable', 'string', 'max:500'],
            'astro' => ['nullable', 'array'],
            'cards' => ['required', 'array', 'size:2'],
            'cards.*.figure_id' => ['required', 'string', 'max:64'],
            'cards.*.figure_name' => ['nullable', 'string', 'max:120'],
            'cards.*.keyword' => ['nullable', 'string', 'max:120'],
            'cards.*.blurb' => ['nullable', 'string', 'max:1200'],
            'cards.*.background_id' => ['required', 'string', 'max:64'],
            'cards.*.background' => ['nullable', 'string', 'max:64'],
            'cards.*.variant' => ['nullable', 'string', 'max:32'],
            'cards.*.polarity' => ['nullable', 'string', 'max:32'],
            'cards.*.deck' => ['nullable', 'string', 'max:32'],
            'cards.*.slot_title' => ['nullable', 'string', 'max:120'],
            'cards.*.astro_caption' => ['nullable', 'string', 'max:8000'],
        ]);

        $mode = $data['mode'] ?? TarotReadingService::MODE_INITIAL;
        $chargeQuestion = $mode === TarotReadingService::MODE_INITIAL;

        if ($chargeQuestion && ! $credits->hasQuestionCredit($user)) {
            return response()->json([
                'error' => __('messages.credits_empty'),
                'credits' => (int) $user->credits,
            ], 402);
        }

        if ($mode === TarotReadingService::MODE_CLARIFY && trim((string) ($data['follow_up'] ?? '')) === '') {
            return response()->json([
                'error' => app()->getLocale() === 'en'
                    ? 'Please write a clarification first.'
                    : 'Írd be a pontosítást.',
            ], 422);
        }

        if (in_array($mode, [TarotReadingService::MODE_CLARIFY, TarotReadingService::MODE_EXPAND], true)
            && trim((string) ($data['previous_reading'] ?? '')) === '') {
            return response()->json([
                'error' => app()->getLocale() === 'en'
                    ? 'Previous reading is missing.'
                    : 'Hiányzik a korábbi olvasat.',
            ], 422);
        }

        try {
            $text = $readings->interpret([
                'question' => $data['question'],
                'locale' => $data['locale'] ?? app()->getLocale(),
                'cards' => $data['cards'],
                'astro' => $data['astro'] ?? [],
                'mode' => $mode,
                'previous_reading' => $data['previous_reading'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'ip' => $request->ip(),
                'user_id' => $user->id,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Az értelmezés most nem elérhető. Próbáld újra később.',
            ], 500);
        }

        $balance = (int) $user->credits;
        if ($chargeQuestion) {
            try {
                $balance = $credits->spendForQuestion($user->fresh());
            } catch (RuntimeException $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'credits' => (int) $user->fresh()->credits,
                ], 402);
            }
        }

        return response()->json([
            'reading' => $text,
            'mode' => $mode,
            'credits' => $balance,
        ]);
    }
}

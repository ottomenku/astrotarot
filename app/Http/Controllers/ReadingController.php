<?php

namespace App\Http\Controllers;

use App\Services\AppSettings;
use App\Services\TarotReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ReadingController extends Controller
{
    public function __invoke(Request $request, TarotReadingService $readings, AppSettings $settings): JsonResponse
    {
        if (! $settings->llmEnabled()) {
            return response()->json([
                'error' => __('messages.llm_disabled'),
            ], 503);
        }

        $minChars = $settings->minQuestionChars();

        $data = $request->validate([
            'question' => ['required', 'string', 'min:'.$minChars, 'max:280'],
            'locale' => ['nullable', 'string', 'in:hu,en'],
            'astro' => ['nullable', 'array'],
            'cards' => ['required', 'array', 'size:3'],
            'cards.*.figure_id' => ['required', 'string', 'max:64'],
            'cards.*.figure_name' => ['nullable', 'string', 'max:120'],
            'cards.*.keyword' => ['nullable', 'string', 'max:120'],
            'cards.*.background_id' => ['required', 'string', 'max:64'],
            'cards.*.background' => ['nullable', 'string', 'max:64'],
            'cards.*.variant' => ['nullable', 'string', 'max:32'],
            'cards.*.polarity' => ['nullable', 'string', 'max:32'],
            'cards.*.astro_caption' => ['nullable', 'string', 'max:180'],
        ]);

        try {
            $text = $readings->interpret([
                'question' => $data['question'],
                'locale' => $data['locale'] ?? app()->getLocale(),
                'cards' => $data['cards'],
                'astro' => $data['astro'] ?? [],
                'ip' => $request->ip(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Az értelmezés most nem elérhető. Próbáld újra később.',
            ], 500);
        }

        return response()->json([
            'reading' => $text,
        ]);
    }
}

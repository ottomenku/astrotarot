<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\ReadingSession;
use App\Services\AppSettings;
use App\Services\GameConfigService;
use App\Services\LlmTranscript;
use App\Services\QuestionClassifier;
use App\Services\SpreadMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ReadingClassifyController extends Controller
{
    public function __invoke(
        Request $request,
        AppSettings $settings,
        QuestionClassifier $classifier,
        SpreadMatcher $matcher,
        GameConfigService $games,
        LlmTranscript $transcript,
    ): JsonResponse {
        set_time_limit(max(180, (int) config('services.openai.timeout', 120) + 30));

        if (! $settings->llmEnabled()) {
            return response()->json([
                'error' => __('messages.llm_disabled'),
            ], 503);
        }

        $minChars = $settings->minQuestionChars();
        $data = $request->validate([
            'question' => ['required', 'string', 'min:'.$minChars, 'max:280'],
            'locale' => ['nullable', 'string', 'in:hu,en'],
        ]);

        $locale = $data['locale'] ?? (app()->getLocale() === 'en' ? 'en' : 'hu');
        $question = trim($data['question']);
        $user = $request->user();

        try {
            $classified = $classifier->classify($locale, $question);
        } catch (RuntimeException) {
            return response()->json([
                'error' => __('messages.classify_failed'),
            ], 503);
        }

        $session = ReadingSession::query()->create([
            'user_id' => $user?->id,
            'locale' => $locale,
            'question' => mb_substr($question, 0, 280),
            'category' => $classified['category'],
            'dimension' => $classified['dimension'],
            'cards' => [],
        ]);
        $session->turns()->create([
            'sequence' => 1,
            'role' => 'user',
            'kind' => 'classify',
            'content' => $question,
        ]);
        $session->turns()->create([
            'sequence' => 2,
            'role' => 'assistant',
            'kind' => 'classify',
            'content' => json_encode($classified, JSON_UNESCAPED_UNICODE),
        ]);
        $transcript->attach($session);

        $game = $matcher->match((string) $classified['category'], (string) $classified['dimension']);
        $game ??= Game::query()->active()->where('is_default', true)->first()
            ?? Game::query()->active()->ordered()->first();
        if ($game === null) {
            return response()->json([
                'status' => 'unmatched',
                'session_id' => $session->public_id,
                'category' => $classified['category'],
                'dimension' => $classified['dimension'],
                'message' => __('messages.classify_unmatched'),
            ]);
        }

        $session->update(['game_id' => $game->id]);
        $category = (string) $classified['category'];
        $dimension = (string) $classified['dimension'];

        return response()->json([
            'status' => 'ready',
            'session_id' => $session->public_id,
            'category' => $category,
            'dimension' => $dimension,
            'game' => $games->homepage($locale, ['game' => $game->id]),
            'needs_user' => $game->sendsAstro(Game::ASTRO_DATA_PERSONAL),
            'needs_relation' => $game->sendsAstro(Game::ASTRO_DATA_RELATIONS)
                && ($category === Game::SPREAD_RELATION || $dimension === Game::DIMENSION_REL),
            'has_user' => $user?->birth_at !== null,
            'profiles' => $user === null
                ? []
                : $user->birthProfiles()->orderBy('label')->get(['id', 'label'])->all(),
        ]);
    }
}

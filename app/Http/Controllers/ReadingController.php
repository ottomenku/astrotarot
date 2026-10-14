<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\PackResolutionService;
use App\Services\TarotReadingService;
use App\Support\FeatureCatalog;
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
        FeatureAccessService $features,
        PackResolutionService $packs,
    ): JsonResponse {
        set_time_limit(max(180, (int) config('services.openai.timeout', 120) + 30));

        if (! $settings->llmEnabled()) {
            return response()->json([
                'error' => __('messages.llm_disabled'),
            ], 503);
        }

        $user = $request->user();

        $minChars = $settings->minQuestionChars();
        $game = Game::query()->find($request->input('game_id'));
        $hasQuestion = $game?->has_question ?? true;
        $slotCount = $game?->rules()->count() ?: null;

        $data = $request->validate([
            'question' => $hasQuestion
                ? ['required', 'string', 'min:'.$minChars, 'max:280']
                : ['nullable', 'string', 'max:280'],
            'locale' => ['nullable', 'string', 'in:hu,en'],
            'mode' => ['nullable', 'string', 'in:initial,clarify,expand'],
            'previous_reading' => ['nullable', 'string', 'max:12000'],
            'follow_up' => ['nullable', 'string', 'max:500'],
            'astro' => ['nullable', 'array'],
            'game_id' => ['nullable', 'integer', 'exists:games,id'],
            'cards' => $slotCount
                ? ['required', 'array', 'size:'.$slotCount]
                : ['required', 'array', 'min:1', 'max:49'],
            'cards.*.figure_id' => ['required', 'string', 'max:64'],
            'cards.*.figure_name' => ['nullable', 'string', 'max:120'],
            'cards.*.keyword' => ['nullable', 'string', 'max:120'],
            'cards.*.blurb' => ['nullable', 'string', 'max:1200'],
            'cards.*.background_id' => ['required', 'string', 'max:64'],
            'cards.*.background' => ['nullable', 'string', 'max:64'],
            'cards.*.variant' => ['nullable', 'string', 'max:32'],
            'cards.*.polarity' => ['nullable', 'string', 'max:32'],
            'cards.*.deck' => ['nullable', 'string', 'max:64'],
            'cards.*.slot_title' => ['nullable', 'string', 'max:120'],
            'cards.*.info_mode' => ['nullable', 'string', 'max:32'],
            'cards.*.row' => ['nullable', 'integer', 'min:1', 'max:12'],
            'cards.*.place' => ['nullable', 'integer', 'min:1', 'max:7'],
            'cards.*.astro_caption' => ['nullable', 'string', 'max:8000'],
        ]);

        $mode = $data['mode'] ?? TarotReadingService::MODE_INITIAL;
        $featureKey = match ($mode) {
            TarotReadingService::MODE_CLARIFY => FeatureCatalog::CLARIFY,
            TarotReadingService::MODE_EXPAND => FeatureCatalog::EXPAND,
            default => FeatureCatalog::READING,
        };
        $decision = $features->decide($user, $featureKey);
        if (! $decision['allowed']) {
            return response()->json([
                'error' => $decision['message'],
                'credits' => (int) ($user?->credits ?? 0),
            ], $decision['status']);
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
            $locale = $data['locale'] ?? app()->getLocale();
            $question = trim((string) ($data['question'] ?? ''));
            if (! $hasQuestion && $question === '') {
                $question = $locale === 'en' ? 'No question for this game.' : 'Ehhez a játékhoz nincs kérdés.';
            }

            $systemPrompt = null;
            if ($mode === TarotReadingService::MODE_INITIAL && $game !== null) {
                $systemPrompt = $game->prompt($locale);
                if ($systemPrompt === '') {
                    $systemPrompt = null;
                }
            }

            $cards = $this->cardsWithGameRules($game, $data['cards'], $locale, $user, $packs);

            $result = $readings->interpret([
                'question' => $question,
                'locale' => $locale,
                'cards' => $cards,
                'astro' => $data['astro'] ?? [],
                'mode' => $mode,
                'previous_reading' => $data['previous_reading'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'system_prompt' => $systemPrompt,
                'ip' => $request->ip(),
                'user_id' => $user?->id,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Az értelmezés most nem elérhető. Próbáld újra később.',
            ], 500);
        }

        $text = $result['text'];
        $source = $result['source'];

        $balance = (int) ($user?->credits ?? 0);
        if ($user !== null) {
            $charge = $features->decide($user->fresh(), $featureKey);
            if ($charge['will_charge']) {
                try {
                    $balance = $credits->spend($user->fresh(), $charge['cost']);
                } catch (RuntimeException $e) {
                    return response()->json([
                        'error' => $e->getMessage(),
                        'credits' => (int) $user->fresh()->credits,
                    ], 402);
                }
            }
        }

        return response()->json([
            'reading' => $text,
            'source' => $source,
            'mode' => $mode,
            'credits' => $balance,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function cardsWithGameRules(?Game $game, array $cards, string $locale, ?User $user, PackResolutionService $packs): array
    {
        if ($game === null) {
            return array_values($cards);
        }

        $game->loadMissing(['rules' => fn ($query) => $query->ordered()->with('defaultPack')]);
        $rules = $game->rules->values();

        foreach ($cards as $index => $card) {
            $rule = $rules->get($index);
            if ($rule === null) {
                continue;
            }
            $cards[$index]['row'] = (int) $rule->row;
            $cards[$index]['place'] = (int) $rule->place;
            $cards[$index]['slot_title'] = $rule->title($locale);
            $cards[$index]['info_mode'] = $rule->info_mode;
            $cards[$index]['deck'] = $packs->allowedDeck(
                $user,
                $game,
                $index,
                (string) ($card['deck'] ?? ''),
            );
        }

        return array_values($cards);
    }
}

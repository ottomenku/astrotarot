<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\PackResolutionService;
use App\Services\TarotReadingService;
use App\Models\GameRule;
use App\Support\CelestialOracleCatalog;
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
            'session_id' => ['nullable', 'uuid'],
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
            'cards.*.variant' => ['nullable', 'string', 'max:32'],
            'cards.*.polarity' => ['nullable', 'string', 'max:32'],
            'cards.*.light_shadow_request' => ['nullable', 'boolean'],
            'cards.*.celestial_helper' => ['nullable', 'string', 'max:32'],
            'cards.*.celestial_polarity' => ['nullable', 'string', 'max:16'],
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

            $spreadPrompt = $game?->prompt($locale) ?? '';

            $cards = $this->cardsWithGameRules($game, $data['cards'], $locale, $user, $packs);

            $result = $readings->interpret([
                'question' => $question,
                'locale' => $locale,
                'cards' => $cards,
                'astro' => $data['astro'] ?? [],
                'mode' => $mode,
                'previous_reading' => $data['previous_reading'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'system_prompt' => $spreadPrompt !== '' ? $spreadPrompt : null,
                'session_id' => $data['session_id'] ?? null,
                'game_id' => $game?->id,
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
            'session_id' => $result['session_id'] ?? null,
            'light_shadow' => $result['light_shadow'] ?? [],
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
            $rule = $this->ruleForCard($rules, $card, $index);
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
            $mode = $rule->effectiveLightShadow($game);
            $cards[$index]['light_shadow_mode'] = $mode;
            $cards[$index]['light_shadow_request'] = $mode === GameRule::LIGHT_SHADOW_LLM;
            $helper = (string) ($card['celestial_helper'] ?? '');
            if (! CelestialOracleCatalog::known($helper)) {
                $helper = '';
            }
            $sendHelper = (bool) $rule->send_celestial_helper
                && (string) ($game->astro_data_mode ?: Game::ASTRO_DATA_NONE) !== Game::ASTRO_DATA_NONE
                && $helper !== '';
            $cards[$index]['celestial_helper'] = $sendHelper ? $helper : '';
            $cards[$index]['include_celestial_helper'] = $sendHelper;
            if ($sendHelper) {
                $polarity = strtolower((string) ($card['celestial_polarity'] ?? ''));
                $cards[$index]['celestial_polarity'] = in_array($polarity, ['shadow', 'negative', 'negatív'], true)
                    ? 'shadow'
                    : 'positive';
            }
        }

        return array_values($cards);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\GameRule>  $rules
     * @param  array<string, mixed>  $card
     */
    private function ruleForCard($rules, array $card, int $index): ?\App\Models\GameRule
    {
        $row = (int) ($card['row'] ?? 0);
        $place = (int) ($card['place'] ?? 0);
        if ($row > 0 && $place > 0) {
            $match = $rules->first(fn ($rule) => (int) $rule->row === $row && (int) $rule->place === $place);
            if ($match !== null) {
                return $match;
            }
        }

        return $rules->get($index);
    }
}

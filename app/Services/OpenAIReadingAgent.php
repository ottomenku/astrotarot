<?php

namespace App\Services;

use App\Models\ReadingSession;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OpenAIReadingAgent
{
    public const AGENT_HU = 'openai_agent_hu';

    public const AGENT_EN = 'openai_agent_en';

    public function __construct(private readonly AppSettings $settings) {}

    public function configured(): bool
    {
        return filter_var(config('services.openai.use_agent'), FILTER_VALIDATE_BOOLEAN)
            && trim((string) config('services.openai.api_key')) !== '';
    }

    public function enabled(): bool
    {
        if (! $this->configured()) {
            return false;
        }

        return $this->settings->effectiveLlmRoute() === AppSettings::LLM_ROUTE_OPENAI;
    }

    public function sync(): ?bool
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $this->ensure('hu');
            $this->ensure('en');

            return true;
        } catch (Throwable $e) {
            Log::warning('OpenAI agent sync failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function completeTurn(ReadingSession $session, string $input, string $extraInstructions): LlmChatResult
    {
        $input = trim($input);
        if ($input === '') {
            throw new RuntimeException('OpenAI agent received an empty reading.');
        }

        $locale = $session->locale === 'en' ? 'en' : 'hu';
        $previousReply = $session->llm_reply_id;

        if (! filled($session->llm_session_id)) {
            $this->openSession($session, $this->ensure($locale), $input, $extraInstructions);
        } else {
            $this->send((string) $session->llm_session_id, $input);
        }

        [$replyId, $text] = $this->waitForReply((string) $session->llm_session_id, $previousReply);
        $session->update(['llm_reply_id' => $replyId]);

        return $this->asResult($text);
    }

    private function ensure(string $locale): string
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $instructions = $this->instructions($locale);
        $tools = $this->tools();
        $hash = hash('sha256', $this->model().'|'.$instructions.'|'.json_encode($tools));
        $idKey = $locale === 'en' ? self::AGENT_EN : self::AGENT_HU;
        $hashKey = $idKey.'_hash';
        $existing = trim((string) $this->settings->get($idKey));

        if ($existing !== '' && $this->settings->get($hashKey) === $hash) {
            return $existing;
        }

        if ($existing !== '') {
            try {
                $this->request('POST', '/agents/'.$existing, [
                    'model' => $this->model(),
                    'instructions' => $instructions,
                    'tools' => $tools,
                ]);
                $this->settings->putMany([$hashKey => $hash]);

                return $existing;
            } catch (Throwable $e) {
                Log::warning('OpenAI agent update failed, creating a new one', ['error' => $e->getMessage()]);
            }
        }

        $created = $this->request('POST', '/agents', [
            'model' => $this->model(),
            'name' => $locale === 'en' ? 'AstroTarot EN' : 'AstroTarot HU',
            'instructions' => $instructions,
            'tools' => $tools,
        ]);
        $id = trim((string) ($created['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('OpenAI agent response did not include an id.');
        }

        if ($existing !== '' && $existing !== $id) {
            try {
                $this->request('DELETE', '/agents/'.$existing);
            } catch (Throwable $e) {
                Log::warning('Old OpenAI agent was left in place', ['error' => $e->getMessage()]);
            }
        }

        $this->settings->putMany([
            $idKey => $id,
            $hashKey => $hash,
        ]);

        return $id;
    }

    private function instructions(string $locale): string
    {
        $system = trim($this->settings->systemPrompt($locale));
        $base = trim($this->settings->basePrompt($locale));
        $header = $locale === 'en' ? '## BASE' : '## ALAPPROMPT';

        $round = $locale === 'en'
            ? 'First round: the message contains the drawn cards and their catalog, and no astrology chart. Take card meanings from that catalog. Search the web for anything the message does not contain. You may ask once in the same reply: {"status":"answer","text":"...","need":"birth|relation|projection","requests":[...]}. The text is the reading they see if they decline. If they accept, the next message has a DATA section; work from that and do not ask again.'
            : 'Első kör: az üzenet a húzott lapokat és a katalógusukat tartalmazza, asztrológiai képlet nincs benne. A lapjelentést a katalógusból vedd. Ami az üzenetből hiányzik, azt keresd a neten. Ugyanebben a válaszban egyszer kérhetsz pontosítást: {"status":"answer","text":"...","need":"birth|relation|projection","requests":[...]}. A text az előzetes olvasat, ezt látja, ha nem pontosít. Ha elfogadja, a következő üzenet ## ADAT része a megadott adat: abból dolgozz, és ne kérj új adatot.';

        return $system."\n\n".$header."\n".$base."\n\n".$round;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        return [[
            'type' => 'web_search',
            'mode' => 'live',
            'context_size' => 'medium',
            'allowed_domains' => null,
            'location' => null,
        ]];
    }

    private function openSession(ReadingSession $session, string $agentId, string $input, string $extraInstructions): void
    {
        $payload = [
            'agent_id' => $agentId,
            'environment' => ['type' => 'none'],
            'input' => $input,
        ];
        $extra = trim($extraInstructions);
        if ($extra !== '') {
            $payload['agent'] = ['instructions' => $extra];
        }

        $created = $this->request('POST', '/agents/sessions', $payload);
        $id = trim((string) ($created['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('OpenAI agent session response did not include an id.');
        }

        $session->update(['llm_session_id' => $id]);
    }

    private function send(string $sessionId, string $input): void
    {
        $this->request('POST', '/agents/sessions/'.$sessionId.'/events', [
            'events' => [[
                'type' => 'agent.session.input.message',
                'input' => [[
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => $input,
                    ]],
                ]],
            ]],
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function waitForReply(string $sessionId, ?string $previousReplyId): array
    {
        $deadline = microtime(true) + max(30, (int) config('services.openai.timeout', 120));
        $previousReplyId = trim((string) $previousReplyId);

        while (microtime(true) < $deadline) {
            $state = $this->request('GET', '/agents/sessions/'.$sessionId);
            $status = (string) ($state['status'] ?? '');
            if ($status === 'failed') {
                throw new RuntimeException('OpenAI agent session failed: '.(string) ($state['error'] ?? ''));
            }
            if ($status === 'requires_action') {
                throw new RuntimeException('OpenAI agent session is waiting for an action this app does not handle.');
            }

            $reply = $this->latestReply($sessionId);
            if ($status === 'idle' && $reply !== null && $reply[0] !== $previousReplyId) {
                return $reply;
            }

            usleep(400000);
        }

        throw new RuntimeException('OpenAI agent session timed out.');
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function latestReply(string $sessionId): ?array
    {
        $page = $this->request('GET', '/agents/sessions/'.$sessionId.'/items', [
            'order' => 'desc',
            'limit' => 20,
        ]);

        foreach ((array) ($page['data'] ?? []) as $item) {
            if (! is_array($item) || ($item['role'] ?? '') !== 'assistant') {
                continue;
            }
            $text = '';
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (is_array($part)) {
                    $text .= (string) ($part['text'] ?? '');
                }
            }
            $text = trim($text);
            $id = trim((string) ($item['id'] ?? ''));
            if ($id !== '' && $text !== '') {
                return [$id, $text];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = [], array $headers = []): array
    {
        $pending = Http::withToken(trim((string) config('services.openai.api_key')))
            ->withHeaders(array_merge(['OpenAI-Beta' => 'agents=v1'], $headers))
            ->acceptJson()
            ->asJson()
            ->timeout(min(60, max(15, (int) config('services.openai.timeout', 120))));

        $url = $this->baseUrl().$path;
        $response = match ($method) {
            'GET' => $pending->get($url, $query),
            'DELETE' => $pending->delete($url),
            default => $pending->post($url, $query),
        };

        if (! $response->successful()) {
            $message = $response->json('error.message');
            $detail = is_string($message) && $message !== '' ? $message : $response->body();
            throw new RuntimeException('OpenAI agent HTTP '.$response->status().': '.$detail);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function asResult(string $text): LlmChatResult
    {
        $body = json_encode([
            'choices' => [
                ['message' => ['content' => $text]],
            ],
        ], JSON_UNESCAPED_UNICODE);

        return new LlmChatResult(
            new HttpResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) $body)),
            'openai',
            $this->model(),
            false,
        );
    }

    private function model(): string
    {
        $model = trim((string) config('services.openai.agent_model', 'gpt-5.4-mini'));

        return $model !== '' ? $model : 'gpt-5.4-mini';
    }

    private function baseUrl(): string
    {
        $base = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        if (str_contains($base, ':11434')) {
            $base = rtrim((string) config('services.openai.cloud_base_url', 'https://api.openai.com/v1'), '/');
        }

        return $base !== '' ? $base : 'https://api.openai.com/v1';
    }
}

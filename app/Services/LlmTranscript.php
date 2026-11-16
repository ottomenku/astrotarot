<?php

namespace App\Services;

use App\Models\LlmExchange;
use App\Models\ReadingLog;
use App\Models\ReadingSession;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Throwable;

class LlmTranscript
{
    private ?int $sessionId = null;

    private ?int $logId = null;

    /**
     * @var list<array{method: string, url: string, request: string, status: ?int, response: string}>
     */
    private array $pending = [];

    public function reset(): void
    {
        $this->sessionId = null;
        $this->logId = null;
        $this->pending = [];
    }

    public function attach(ReadingSession $session, ?ReadingLog $log = null): void
    {
        $this->sessionId = $session->id;
        if ($log !== null) {
            $this->logId = $log->id;
        }
        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(PendingRequest $pending, string $method, string $url, array $payload = []): Response
    {
        $method = strtoupper($method);
        $captured = null;
        $pending = $pending->beforeSending(function ($request) use (&$captured) {
            $psr = $request->toPsrRequest();
            $stream = $psr->getBody();
            $captured = [
                'method' => $psr->getMethod(),
                'url' => (string) $psr->getUri(),
                'body' => (string) $stream,
            ];
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
        });

        try {
            $response = match ($method) {
                'GET' => $pending->get($url, $payload),
                'DELETE' => $pending->delete($url, $payload),
                default => $pending->post($url, $payload),
            };
        } catch (Throwable $e) {
            $this->record(
                $captured['method'] ?? $method,
                $captured['url'] ?? $url,
                is_array($captured) ? $captured['body'] : $this->fallbackBody($method, $payload),
                null,
                $e->getMessage(),
            );
            throw $e;
        }

        $this->record(
            $captured['method'] ?? $method,
            $captured['url'] ?? $url,
            is_array($captured) ? $captured['body'] : $this->fallbackBody($method, $payload),
            $response->status(),
            $response->body(),
        );

        return $response;
    }

    public function record(string $method, string $url, string $requestBody, ?int $status, string $responseBody): void
    {
        $row = [
            'method' => $method,
            'url' => $url,
            'request' => $requestBody,
            'status' => $status,
            'response' => $responseBody,
        ];

        if ($this->sessionId === null && $this->logId === null) {
            $this->pending[] = $row;

            return;
        }

        $this->store($row);
    }

    private function flush(): void
    {
        foreach ($this->pending as $row) {
            $this->store($row);
        }
        $this->pending = [];
    }

    /**
     * @param  array{method: string, url: string, request: string, status: ?int, response: string}  $row
     */
    private function store(array $row): void
    {
        $query = LlmExchange::query();
        if ($this->sessionId !== null) {
            $query->where('reading_session_id', $this->sessionId);
        } else {
            $query->where('reading_log_id', $this->logId);
        }

        LlmExchange::query()->create([
            'reading_session_id' => $this->sessionId,
            'reading_log_id' => $this->logId,
            'sequence' => ((int) $query->max('sequence')) + 1,
            'method' => $row['method'],
            'url' => $row['url'],
            'request_body' => self::readable($row['request']),
            'status' => $row['status'],
            'response_body' => self::readable($row['response']),
        ]);
    }

    public static function readable(string $body): string
    {
        $trim = trim($body);
        if ($trim === '') {
            return '';
        }

        $decoded = json_decode($trim, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $encoded = json_encode(
                $decoded,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            return is_string($encoded) ? self::breakEscapedNewlines($encoded) : $body;
        }

        $decodedEscapes = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $match): string {
                $char = mb_chr(hexdec($match[1]), 'UTF-8');

                return $char === false ? $match[0] : $char;
            },
            $body,
        );

        return is_string($decodedEscapes) ? self::breakLiteralNewlineMarks($decodedEscapes) : $body;
    }

    /**
     * Turn JSON string escapes into real breaks. The wire body stays compact;
     * only the stored copy is wrapped this way.
     */
    private static function breakEscapedNewlines(string $json): string
    {
        $out = '';
        $length = strlen($json);
        $inString = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if (! $inString) {
                $out .= $char;
                if ($char === '"') {
                    $inString = true;
                }

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $next = $json[$i + 1];
                if ($next === 'n') {
                    $out .= "\n";
                    $i++;

                    continue;
                }
                if ($next === 'r') {
                    $skipN = $i + 3 < $length && $json[$i + 2] === '\\' && $json[$i + 3] === 'n';
                    $out .= "\n";
                    $i += $skipN ? 3 : 1;

                    continue;
                }
                if ($next === 't') {
                    $out .= "\t";
                    $i++;

                    continue;
                }

                $out .= $char.$next;
                $i++;

                continue;
            }

            if ($char === '"') {
                $inString = false;
            }
            $out .= $char;
        }

        return self::breakLiteralNewlineMarks($out);
    }

    private static function breakLiteralNewlineMarks(string $text): string
    {
        $text = str_replace(['\\\\r\\\\n', '\\\\n', '\\\\r'], "\n", $text);

        return str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fallbackBody(string $method, array $payload): string
    {
        if ($method === 'GET' || $method === 'DELETE' || $payload === []) {
            return '';
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

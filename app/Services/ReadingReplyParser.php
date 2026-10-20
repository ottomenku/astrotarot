<?php

namespace App\Services;

class ReadingReplyParser
{
    /**
     * @return array{
     *   status: string,
     *   text: string,
     *   requests: list<array<string, mixed>>,
     *   question_type: ?string,
     *   spread_type: ?string
     * }
     */
    public function parse(string $raw): array
    {
        $trimmed = trim($raw);
        $json = $this->extractJson($trimmed);

        if ($json === null) {
            return $this->answer($trimmed, null, null);
        }

        $questionType = $this->shortLabel($json['question_type'] ?? null);
        $spreadType = $this->shortLabel($json['spread_type'] ?? null);
        $status = strtolower(trim((string) ($json['status'] ?? '')));

        if (in_array($status, ['need_data', 'data_request', 'request'], true)) {
            $requests = $this->requests($json['requests'] ?? []);
            if ($requests !== []) {
                return [
                    'status' => 'need_data',
                    'text' => trim((string) ($json['text'] ?? '')),
                    'requests' => $requests,
                    'question_type' => $questionType,
                    'spread_type' => $spreadType,
                ];
            }
        }

        $text = trim((string) ($json['text'] ?? ''));
        if ($text === '' && $status !== 'answer' && $status !== 'reading') {
            $text = $trimmed;
        }

        return $this->answer($text, $questionType, $spreadType);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $candidate = $raw;
        if (str_starts_with($candidate, '```')) {
            $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate) ?? $candidate;
            $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
            $candidate = trim($candidate);
        }

        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($candidate, '{');
        $end = strrpos($candidate, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($candidate, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requests(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $requests = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = strtolower(trim((string) ($row['kind'] ?? '')));
            if (! in_array($kind, ['chart', 'houses', 'projection'], true)) {
                continue;
            }
            $include = [];
            foreach ((array) ($row['include'] ?? []) as $item) {
                $item = strtolower(trim((string) $item));
                if (in_array($item, ['planets', 'signs', 'aspects', 'fixed_stars', 'houses'], true)) {
                    $include[] = $item;
                }
            }

            $requests[] = [
                'id' => trim((string) ($row['id'] ?? (string) (count($requests) + 1))),
                'kind' => $kind,
                'moment' => trim((string) ($row['moment'] ?? 'now')),
                'include' => array_values(array_unique($include)),
                'body' => trim((string) ($row['body'] ?? '')),
                'event' => strtolower(trim((string) ($row['event'] ?? ''))),
                'aspect' => trim((string) ($row['aspect'] ?? '')),
                'with' => trim((string) ($row['with'] ?? '')),
                'house' => is_numeric($row['house'] ?? null) ? (int) $row['house'] : null,
                'from' => trim((string) ($row['from'] ?? '')),
                'until' => trim((string) ($row['until'] ?? '')),
            ];

            if (count($requests) >= 4) {
                break;
            }
        }

        return $requests;
    }

    /**
     * @return array{
     *   status: string,
     *   text: string,
     *   requests: list<array<string, mixed>>,
     *   question_type: ?string,
     *   spread_type: ?string
     * }
     */
    private function answer(string $text, ?string $questionType, ?string $spreadType): array
    {
        return [
            'status' => 'answer',
            'text' => $text,
            'requests' => [],
            'question_type' => $questionType,
            'spread_type' => $spreadType,
        ];
    }

    private function shortLabel(mixed $value): ?string
    {
        $label = trim((string) $value);

        if ($label === '') {
            return null;
        }

        return mb_substr($label, 0, 80);
    }
}

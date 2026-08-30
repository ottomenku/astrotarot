<?php

namespace App\Support;

final class HtmlSanitizer
{
    public static function planBody(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><a><h2><h3><h4><span>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        $html = preg_replace_callback(
            '#<ul([^>]*)>#i',
            static function (array $match): string {
                $attrs = $match[1];
                if (preg_match('/class\s*=\s*("|\')([^"\']*)\1/i', $attrs, $class)) {
                    $safe = preg_replace('/[^a-z0-9 _-]+/i', '', $class[2]) ?? '';
                    $safe = trim(preg_replace('/\s+/', ' ', $safe) ?? '');
                    if ($safe !== '') {
                        return '<ul class="'.$safe.'">';
                    }
                }

                return '<ul>';
            },
            $html
        ) ?? $html;

        return trim($html);
    }
}

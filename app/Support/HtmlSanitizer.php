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

    public static function articleBody(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><a><h2><h3><h4><blockquote><img>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        $html = preg_replace_callback(
            '#<a\b([^>]*)>#i',
            static function (array $match): string {
                $attrs = $match[1];
                $href = '';
                if (preg_match('/href\s*=\s*("|\')([^"\']*)\1/i', $attrs, $hrefMatch)) {
                    $href = trim($hrefMatch[2]);
                }
                if ($href === '' || ! preg_match('#^(https?:)?/#i', $href) || preg_match('#^\s*javascript:#i', $href)) {
                    return '<a>';
                }

                return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" rel="noopener noreferrer">';
            },
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '#<img\b([^>]*)>#i',
            static function (array $match): string {
                $attrs = $match[1];
                $src = '';
                $alt = '';
                if (preg_match('/src\s*=\s*("|\')([^"\']*)\1/i', $attrs, $srcMatch)) {
                    $src = trim($srcMatch[2]);
                }
                if (preg_match('/alt\s*=\s*("|\')([^"\']*)\1/i', $attrs, $altMatch)) {
                    $alt = trim($altMatch[2]);
                }
                if ($src === '' || preg_match('#^\s*javascript:#i', $src) || ! preg_match('#^(https?:/)?/#i', $src)) {
                    return '';
                }

                return '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($alt, ENT_QUOTES, 'UTF-8').'">';
            },
            $html
        ) ?? $html;

        return trim($html);
    }
}

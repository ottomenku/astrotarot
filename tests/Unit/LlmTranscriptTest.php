<?php

namespace Tests\Unit;

use App\Services\LlmTranscript;
use PHPUnit\Framework\TestCase;

class LlmTranscriptTest extends TestCase
{
    public function test_readable_json_is_indented_and_unicode_escapes_become_characters(): void
    {
        $raw = '{"text":"\u00e1\u0020vil\u00e1g\u2014"}';

        $text = LlmTranscript::readable($raw);

        $this->assertStringContainsString("á világ—", $text);
        $this->assertStringContainsString("\n    \"text\":", $text);
        $this->assertStringNotContainsString('\u00', $text);
        $this->assertSame($text, LlmTranscript::readable($text));
    }

    public function test_saved_copy_turns_newline_escapes_into_line_breaks(): void
    {
        $raw = '{"messages":[{"role":"system","content":"Első sor\\nMásodik sor\\r\\nHarmadik"}]}';

        $text = LlmTranscript::readable($raw);

        $this->assertStringContainsString("Első sor\nMásodik sor\nHarmadik", $text);
        $this->assertStringNotContainsString('\\n', $text);
        $this->assertStringContainsString('"role": "system"', $text);
        $this->assertSame($text, LlmTranscript::readable($text));
    }

    public function test_saved_copy_turns_a_written_backslash_n_into_a_line_break(): void
    {
        $raw = '{"text":"sor\\\\nketto"}';

        $text = LlmTranscript::readable($raw);

        $this->assertStringContainsString("sor\nketto", $text);
        $this->assertStringNotContainsString('\\', $text);
        $this->assertSame($text, LlmTranscript::readable($text));
    }
}

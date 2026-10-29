<?php

namespace Tests\Unit;

use App\Services\ReadingReplyParser;
use Tests\TestCase;

class ReadingReplyParserTest extends TestCase
{
    public function test_plain_text_is_an_answer(): void
    {
        $parsed = (new ReadingReplyParser)->parse('A Mars azt mondja, hogy lépj.');

        $this->assertSame('answer', $parsed['status']);
        $this->assertSame('A Mars azt mondja, hogy lépj.', $parsed['text']);
        $this->assertSame([], $parsed['requests']);
    }

    public function test_data_request_json_is_kept_and_unknown_kinds_are_dropped(): void
    {
        $raw = <<<'JSON'
```json
{"status":"need_data","question_type":"időzítés","spread_type":"döntés","requests":[
  {"id":"1","kind":"chart","moment":"now","include":["planets","poem"]},
  {"id":"2","kind":"horoscope"},
  {"kind":"projection","body":"Mars","event":"retrograde","from":"2026-10-20T00:00:00Z","until":"2026-11-20T00:00:00Z"}
]}
```
JSON;

        $parsed = (new ReadingReplyParser)->parse($raw);

        $this->assertSame('need_data', $parsed['status']);
        $this->assertSame('időzítés', $parsed['question_type']);
        $this->assertCount(2, $parsed['requests']);
        $this->assertSame(['planets'], $parsed['requests'][0]['include']);
        $this->assertSame('projection', $parsed['requests'][1]['kind']);
        $this->assertSame('2', $parsed['requests'][1]['id']);
    }

    public function test_answer_json_uses_the_text_field(): void
    {
        $parsed = (new ReadingReplyParser)->parse('{"status":"answer","text":"Kész olvasat."}');

        $this->assertSame('answer', $parsed['status']);
        $this->assertSame('Kész olvasat.', $parsed['text']);
        $this->assertSame([], $parsed['light_shadow']);
    }

    public function test_answer_json_keeps_light_shadow_decisions(): void
    {
        $parsed = (new ReadingReplyParser)->parse('{"status":"answer","text":"Kész.","light_shadow":[{"slot":1,"row":1,"place":2,"value":"shadow"},{"value":"poem"},{"slot":2,"value":"light"}]}');

        $this->assertSame('answer', $parsed['status']);
        $this->assertCount(2, $parsed['light_shadow']);
        $this->assertSame(1, $parsed['light_shadow'][0]['slot']);
        $this->assertSame(2, $parsed['light_shadow'][0]['place']);
        $this->assertSame('shadow', $parsed['light_shadow'][0]['value']);
        $this->assertSame('light', $parsed['light_shadow'][1]['value']);
    }

    public function test_data_request_without_usable_requests_becomes_an_answer(): void
    {
        $parsed = (new ReadingReplyParser)->parse('{"status":"need_data","text":"Mégis ez a válasz.","requests":[]}');

        $this->assertSame('answer', $parsed['status']);
        $this->assertSame('Mégis ez a válasz.', $parsed['text']);
    }
}

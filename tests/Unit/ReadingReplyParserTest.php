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
    }

    public function test_data_request_without_usable_requests_becomes_an_answer(): void
    {
        $parsed = (new ReadingReplyParser)->parse('{"status":"need_data","text":"Mégis ez a válasz.","requests":[]}');

        $this->assertSame('answer', $parsed['status']);
        $this->assertSame('Mégis ez a válasz.', $parsed['text']);
    }
}

<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Services\ReadingCostEstimate;
use Tests\TestCase;

class ReadingCostEstimateTest extends TestCase
{
    public function test_gpt_5_4_mini_price_uses_list_rate_and_character_tokens(): void
    {
        $log = new ReadingLog([
            'model' => 'gpt-5.4-mini',
            'llm_provider' => 'openai',
            'system_prompt' => str_repeat('a', 300),
            'user_prompt' => str_repeat('b', 300),
            'response' => str_repeat('c', 300),
        ]);

        $cost = (new ReadingCostEstimate)->forLog($log);

        $this->assertSame(200, $cost['input_tokens']);
        $this->assertSame(100, $cost['output_tokens']);
        $this->assertSame(300, $cost['total_tokens']);
        $usd = (200 * 0.75 / 1_000_000) + (100 * 4.50 / 1_000_000);
        $this->assertEqualsWithDelta($usd * ReadingCostEstimate::USD_HUF, $cost['huf'], 0.000_001);
        $this->assertSame('0,20 Ft', $cost['huf_label']);
    }

    public function test_snapshot_ids_match_the_base_model_and_local_models_are_free(): void
    {
        $priced = (new ReadingCostEstimate)->forLog(new ReadingLog([
            'model' => 'gpt-4o-mini-2024-07-18',
            'llm_provider' => 'openai',
            'system_prompt' => 'abc',
            'user_prompt' => '',
            'response' => 'abcd',
        ]));
        $this->assertNotNull($priced['huf']);

        $local = (new ReadingCostEstimate)->forLog(new ReadingLog([
            'model' => 'qwen2.5:14b',
            'llm_provider' => 'ollama',
            'system_prompt' => 'abc',
            'user_prompt' => 'def',
            'response' => 'ghi',
        ]));
        $this->assertNull($local['huf']);
        $this->assertTrue($local['local']);
    }
}

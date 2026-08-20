<?php

namespace Tests\Unit;

use App\Services\TarotReadingService;
use Tests\TestCase;

class MeaninglessQuestionTest extends TestCase
{
    public function test_detects_keyboard_mash_and_allows_real_questions(): void
    {
        /** @var TarotReadingService $service */
        $service = $this->app->make(TarotReadingService::class);

        $this->assertTrue($service->looksMeaningless('asdfghjklqwerty'));
        $this->assertTrue($service->looksMeaningless('kjhgfdsaoiuytrewq'));
        $this->assertTrue($service->looksMeaningless('!!!!!!!!!!!??????'));
        $this->assertTrue($service->looksMeaningless('xxxxxxxxxxxxxxxx'));

        $this->assertFalse($service->looksMeaningless('Mit tegyek most a kapcsolatommal?'));
        $this->assertFalse($service->looksMeaningless('What should I do about my career?'));
        $this->assertFalse($service->looksMeaningless('Hogyan találjak békét magamban?'));
    }
}

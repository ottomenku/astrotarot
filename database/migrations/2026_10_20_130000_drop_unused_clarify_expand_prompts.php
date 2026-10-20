<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->whereIn('key', [
            'clarify_prompt_hu',
            'clarify_prompt_en',
            'expand_prompt_hu',
            'expand_prompt_en',
        ])->delete();

        Cache::forget('app.settings.bag');
    }

    public function down(): void
    {
        // The clarify and expand prompts are no longer part of the reading.
    }
};

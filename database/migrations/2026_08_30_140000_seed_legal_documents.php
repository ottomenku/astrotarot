<?php

use App\Services\AppSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = app(AppSettings::class);
        $values = [
            AppSettings::TERMS_HU => $settings->defaultTermsHu(),
            AppSettings::TERMS_EN => $settings->defaultTermsEn(),
            AppSettings::PRIVACY_HU => $settings->defaultPrivacyHu(),
            AppSettings::PRIVACY_EN => $settings->defaultPrivacyEn(),
        ];

        $toPut = [];
        foreach ($values as $key => $value) {
            $existing = $settings->get($key);
            if ($existing === null || trim((string) $existing) === '') {
                $toPut[$key] = $value;
            }
        }

        if ($toPut !== []) {
            $settings->putMany($toPut);
        }
    }

    public function down(): void
    {
        // Keep published legal texts; they are content, not schema.
    }
};

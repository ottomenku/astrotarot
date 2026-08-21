<?php

namespace App\Console\Commands;

use App\Services\OgImageService;
use Illuminate\Console\Command;

class GeneratePromoOgImage extends Command
{
    protected $signature = 'og:promo-image';

    protected $description = 'Generate the Facebook OG image (Venus shadow + Cups 04 shadow)';

    public function handle(OgImageService $ogImages): int
    {
        $jpeg = $ogImages->makeTwoCardJpeg([
            ['deck' => 'oracle', 'figure_id' => 'venus', 'variant' => 'shadow'],
            ['deck' => 'tarot', 'figure_id' => 'cups-04', 'variant' => 'shadow'],
        ]);

        if ($jpeg === null) {
            $this->error('Could not generate promo OG image. Check GD and card assets.');

            return self::FAILURE;
        }

        $outDir = public_path('og');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error('Cannot create public/og directory.');

            return self::FAILURE;
        }

        $outPath = $outDir.'/astrotarot-promo.jpg';
        if (file_put_contents($outPath, $jpeg) === false) {
            $this->error('Could not write '.$outPath);

            return self::FAILURE;
        }

        $this->info('Wrote '.$outPath);

        return self::SUCCESS;
    }
}

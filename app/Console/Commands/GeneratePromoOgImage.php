<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePromoOgImage extends Command
{
    protected $signature = 'og:promo-image';

    protected $description = 'Generate the Facebook OG image (Venus shadow + Cups 04 shadow)';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('PHP GD extension is required.');

            return self::FAILURE;
        }

        $outDir = public_path('og');
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            $this->error('Cannot create public/og directory.');

            return self::FAILURE;
        }

        $bgPath = public_path('templates/art-deco/assets/egi-orakulum/backgrounds/shared-shadow.png');
        $venusPath = public_path('templates/art-deco/assets/egi-orakulum/figures/venus.png');
        $cupsPath = public_path('templates/art-deco/assets/tarot-major/figures/minors/cups-04.png');

        foreach ([$bgPath, $venusPath, $cupsPath] as $path) {
            if (! is_file($path)) {
                $this->error('Missing asset: '.$path);

                return self::FAILURE;
            }
        }

        $width = 1200;
        $height = 630;
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            $this->error('Could not create canvas.');

            return self::FAILURE;
        }

        $ink = imagecolorallocate($canvas, 9, 10, 20);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $ink);

        $cardW = 360;
        $cardH = 540;
        $gap = 48;
        $total = ($cardW * 2) + $gap;
        $startX = (int) (($width - $total) / 2);
        $startY = (int) (($height - $cardH) / 2);

        $left = $this->composeCard($bgPath, $venusPath, $cardW, $cardH);
        $right = $this->composeCard($bgPath, $cupsPath, $cardW, $cardH);
        if ($left === null || $right === null) {
            imagedestroy($canvas);
            $this->error('Could not compose cards.');

            return self::FAILURE;
        }

        imagecopy($canvas, $left, $startX, $startY, 0, 0, $cardW, $cardH);
        imagecopy($canvas, $right, $startX + $cardW + $gap, $startY, 0, 0, $cardW, $cardH);
        imagedestroy($left);
        imagedestroy($right);

        $gold = imagecolorallocate($canvas, 217, 184, 110);
        $label = 'AstroTarot';
        imagestring($canvas, 5, (int) (($width - (strlen($label) * 9)) / 2), 18, $label, $gold);

        $outPath = $outDir.'/astrotarot-promo.jpg';
        if (! imagejpeg($canvas, $outPath, 88)) {
            imagedestroy($canvas);
            $this->error('Could not write '.$outPath);

            return self::FAILURE;
        }

        imagedestroy($canvas);
        $this->info('Wrote '.$outPath);

        return self::SUCCESS;
    }

    /**
     * @return \GdImage|null
     */
    private function composeCard(string $backgroundPath, string $figurePath, int $width, int $height)
    {
        $bg = @imagecreatefrompng($backgroundPath);
        $figure = @imagecreatefrompng($figurePath);
        if ($bg === false || $figure === false) {
            return null;
        }

        $card = imagecreatetruecolor($width, $height);
        if ($card === false) {
            imagedestroy($bg);
            imagedestroy($figure);

            return null;
        }

        imagealphablending($card, true);
        imagesavealpha($card, true);

        imagecopyresampled($card, $bg, 0, 0, 0, 0, $width, $height, imagesx($bg), imagesy($bg));

        $fw = imagesx($figure);
        $fh = imagesy($figure);
        $scale = min(($width * 0.88) / $fw, ($height * 0.88) / $fh);
        $dw = (int) round($fw * $scale);
        $dh = (int) round($fh * $scale);
        $dx = (int) round(($width - $dw) / 2);
        $dy = (int) round(($height - $dh) / 2);
        imagecopyresampled($card, $figure, $dx, $dy, 0, 0, $dw, $dh, $fw, $fh);

        imagedestroy($bg);
        imagedestroy($figure);

        return $card;
    }
}

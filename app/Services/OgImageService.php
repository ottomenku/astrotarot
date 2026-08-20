<?php

namespace App\Services;

class OgImageService
{
    public function canGenerate(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefrompng');
    }

    public function figureAbsolutePath(string $deck, string $figureId): ?string
    {
        if ($figureId === '') {
            return null;
        }

        $folder = $deck === 'tarot' ? 'tarot-major' : 'egi-orakulum';
        if ($deck === 'tarot' && preg_match('/^(wands|cups|swords|pentacles)-/', $figureId) === 1) {
            $relative = "templates/art-deco/assets/{$folder}/figures/minors/{$figureId}.png";
        } else {
            $relative = "templates/art-deco/assets/{$folder}/figures/{$figureId}.png";
        }

        $absolute = public_path($relative);

        return is_file($absolute) ? $absolute : null;
    }

    public function figurePublicUrl(string $deck, string $figureId): ?string
    {
        if ($figureId === '') {
            return null;
        }

        $folder = $deck === 'tarot' ? 'tarot-major' : 'egi-orakulum';
        if ($deck === 'tarot' && preg_match('/^(wands|cups|swords|pentacles)-/', $figureId) === 1) {
            $relative = "templates/art-deco/assets/{$folder}/figures/minors/{$figureId}.png";
        } else {
            $relative = "templates/art-deco/assets/{$folder}/figures/{$figureId}.png";
        }

        if (! is_file(public_path($relative))) {
            return null;
        }

        return asset($relative);
    }

    public function backgroundAbsolutePath(string $variant): string
    {
        $tone = $variant === 'shadow' || $variant === 'negative' ? 'shadow' : 'light';

        return public_path("templates/art-deco/assets/egi-orakulum/backgrounds/shared-{$tone}.png");
    }

    /**
     * @param  list<array{deck?: string, figure_id?: string, variant?: string, polarity?: string}>  $cards
     */
    public function makeTwoCardJpeg(array $cards, int $width = 1200, int $height = 630): ?string
    {
        if (! $this->canGenerate() || count($cards) < 2) {
            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            return null;
        }

        $ink = imagecolorallocate($canvas, 9, 10, 20);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $ink);

        $cardW = 360;
        $cardH = 540;
        $gap = 48;
        $total = ($cardW * 2) + $gap;
        $startX = (int) (($width - $total) / 2);
        $startY = (int) (($height - $cardH) / 2);

        foreach (array_slice($cards, 0, 2) as $index => $card) {
            $deck = (string) ($card['deck'] ?? ($index === 0 ? 'oracle' : 'tarot'));
            $figureId = (string) ($card['figure_id'] ?? '');
            $variant = (string) ($card['variant'] ?? $card['polarity'] ?? 'positive');
            $figurePath = $this->figureAbsolutePath($deck, $figureId);
            $bgPath = $this->backgroundAbsolutePath($variant);
            if ($figurePath === null || ! is_file($bgPath)) {
                imagedestroy($canvas);

                return null;
            }

            $composed = $this->composeCard($bgPath, $figurePath, $cardW, $cardH);
            if ($composed === null) {
                imagedestroy($canvas);

                return null;
            }

            $x = $startX + ($index * ($cardW + $gap));
            imagecopy($canvas, $composed, $x, $startY, 0, 0, $cardW, $cardH);
            imagedestroy($composed);
        }

        $gold = imagecolorallocate($canvas, 217, 184, 110);
        $label = 'AstroTarot';
        imagestring($canvas, 5, (int) (($width - (strlen($label) * 9)) / 2), 18, $label, $gold);

        ob_start();
        imagejpeg($canvas, null, 88);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        return is_string($binary) && $binary !== '' ? $binary : null;
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

<?php

namespace App\Services;

use App\Support\CardPackCatalog;

class OgImageService
{
    public function canGenerate(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefrompng');
    }

    public function figureAbsolutePath(string $deck, string $figureId, ?string $variant = null): ?string
    {
        $relative = $this->figureRelativePath($deck, $figureId, $variant);

        return $relative === null ? null : public_path($relative);
    }

    public function figurePublicUrl(string $deck, string $figureId, ?string $variant = null): ?string
    {
        $relative = $this->figureRelativePath($deck, $figureId, $variant);

        return $relative === null ? null : asset($relative);
    }

    public function figureRelativePath(string $deck, string $figureId, ?string $variant = null): ?string
    {
        return CardPackCatalog::figureRelative($deck, $figureId, $variant);
    }

    public function backgroundAbsolutePath(string $variant, string $deck = 'egi-orakulum'): ?string
    {
        $relative = CardPackCatalog::backgroundRelative($deck, $variant);

        return $relative === null ? null : public_path($relative);
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
            $figurePath = $this->figureAbsolutePath($deck, $figureId, $variant);
            $bgPath = $this->backgroundAbsolutePath($variant, $deck);
            if ($figurePath === null) {
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
    private function composeCard(?string $backgroundPath, string $figurePath, int $width, int $height)
    {
        $figure = @imagecreatefrompng($figurePath);
        if ($figure === false) {
            return null;
        }

        $card = imagecreatetruecolor($width, $height);
        if ($card === false) {
            imagedestroy($figure);

            return null;
        }

        imagealphablending($card, true);
        imagesavealpha($card, true);

        $bg = ($backgroundPath !== null && is_file($backgroundPath))
            ? @imagecreatefrompng($backgroundPath)
            : false;

        if ($bg !== false) {
            imagecopyresampled($card, $bg, 0, 0, 0, 0, $width, $height, imagesx($bg), imagesy($bg));
            imagedestroy($bg);
        } else {
            $ink = imagecolorallocate($card, 14, 13, 28);
            imagefilledrectangle($card, 0, 0, $width, $height, $ink);
        }

        $fw = imagesx($figure);
        $fh = imagesy($figure);
        $fullBleed = $bg === false;
        $scale = $fullBleed
            ? max($width / $fw, $height / $fh)
            : min(($width * 0.88) / $fw, ($height * 0.88) / $fh);
        $dw = (int) round($fw * $scale);
        $dh = (int) round($fh * $scale);
        $dx = (int) round(($width - $dw) / 2);
        $dy = (int) round(($height - $dh) / 2);
        imagecopyresampled($card, $figure, $dx, $dy, 0, 0, $dw, $dh, $fw, $fh);

        imagedestroy($figure);

        return $card;
    }
}

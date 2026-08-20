<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PromoShareController extends Controller
{
    public function __invoke(): View
    {
        $ogTitle = 'AstroTarot';
        $ogDescription = 'Tedd fel a kérdésed, válassz Égi mestert, húzz egy tarot lapot és figyelj a válaszra! Most bevezető promócióban ajándék prémium csomag!';
        $shareUrl = route('promo.share');
        $ogImage = asset('og/astrotarot-promo.jpg');

        return view('exports.promo-share', [
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'shareUrl' => $shareUrl,
            'assetBase' => asset('templates/art-deco'),
        ]);
    }
}

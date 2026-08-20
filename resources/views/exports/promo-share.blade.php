<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ogTitle }}</title>
    <meta name="description" content="{{ $ogDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="AstroTarot">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
    <style>
        .promo-share { max-width:720px; margin:0 auto; padding:36px 18px 80px; text-align:center; }
        .promo-share h1 { margin:0 0 12px; color:var(--gold-pale); font:italic clamp(28px,5vw,40px)/1.15 Georgia,serif; }
        .promo-share p { margin:0 0 18px; color:#cfc9d6; font:16px/1.5 Georgia,serif; }
        .promo-share img { width:min(100%,640px); height:auto; border:1px solid rgba(217,184,110,.35); margin:18px 0 24px; }
        .promo-share a.cta { display:inline-block; padding:12px 18px; border:1px solid rgba(217,184,110,.5); color:var(--gold-pale); text-decoration:none; font:14px Georgia,serif; }
    </style>
</head>
<body>
<main class="app-shell">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
                <span class="brand-name">Astro<span>Tarot</span></span>
            </a>
        </div>
    </header>
    <section class="promo-share">
        <h1>{{ $ogTitle }}</h1>
        <p>{{ $ogDescription }}</p>
        <img src="{{ $ogImage }}" width="1200" height="630" alt="AstroTarot – Vénusz és Cups 04 shadow">
        <p>
            <a class="cta" href="{{ route('home') }}">{{ app()->getLocale() === 'en' ? 'Open AstroTarot' : 'Tovább az AstroTarothoz' }}</a>
        </p>
    </section>
</main>
</body>
</html>

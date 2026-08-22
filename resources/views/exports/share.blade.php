<!DOCTYPE html>
<html lang="{{ $payload['locale'] ?? 'hu' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ogTitle }} · AstroTarot</title>
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
        .share-shell { max-width:820px; margin:0 auto; padding:28px 18px 80px; }
        .share-shell h1 { margin:0 0 10px; color:var(--gold-pale); font:italic clamp(26px,5vw,36px)/1.15 Georgia,serif; }
        .share-kicker { margin:0 0 8px; color:var(--gold); font-size:10px; letter-spacing:2px; text-transform:uppercase; }
        .share-question { margin:0 0 22px; color:#cfc9d6; font:italic 16px/1.45 Georgia,serif; }
        .share-cards { display:grid; gap:16px; margin-bottom:28px; }
        @media (min-width:720px) { .share-cards { grid-template-columns:1fr 1fr; } }
        .share-card { padding:14px; border:1px solid rgba(217,184,110,.28); background:rgba(255,255,255,.03); }
        .share-card-art { width:min(100%,220px); margin:0 auto 12px; display:block; border:1px solid rgba(217,184,110,.25); }
        .share-card h2 { margin:0 0 6px; color:var(--gold-pale); font:italic 18px Georgia,serif; }
        .share-card p { margin:0 0 8px; color:#b9b4c0; font:13px/1.45 Georgia,serif; white-space:pre-wrap; }
        .share-answer { margin:0 0 22px; padding-top:16px; border-top:1px solid rgba(217,184,110,.22); }
        .share-answer h3 { margin:0 0 8px; color:var(--gold); font:12px Georgia,serif; letter-spacing:1px; text-transform:uppercase; }
        .share-answer p { margin:0; color:var(--paper); font:15px/1.65 Georgia,serif; white-space:pre-wrap; }
        .share-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:24px; }
        .share-actions a { color:var(--gold-pale); text-decoration:none; border:1px solid rgba(217,184,110,.35); padding:10px 14px; font:13px Georgia,serif; }
        .share-og-preview { width:min(100%,640px); height:auto; margin:0 0 22px; border:1px solid rgba(217,184,110,.28); }
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

    <section class="share-shell">
        <p class="share-kicker">AstroTarot</p>
        <h1>{{ $ogTitle }}</h1>
        <p class="share-question">{{ $payload['question'] ?? '' }}</p>
        <img class="share-og-preview" src="{{ $ogImage }}" width="1200" height="630" alt="AstroTarot húzás">

        <div class="share-cards">
            @foreach (($payload['cards'] ?? []) as $card)
                <article class="share-card">
                    @if (!empty($card['image_url']))
                        <img class="share-card-art" src="{{ $card['image_url'] }}" alt="{{ $card['figure_name'] ?? '' }}">
                    @endif
                    <h2>{{ $card['slot_title'] ?? '' }}</h2>
                    <p><strong>{{ $card['figure_name'] ?? '' }}</strong>
                        @if (!empty($card['polarity'])) · {{ $card['polarity'] }} @endif
                    </p>
                    @if (!empty($card['astro_caption']))
                        <p>{{ $card['astro_caption'] }}</p>
                    @elseif (!empty($card['blurb']))
                        <p>{{ $card['blurb'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>

        @foreach (($payload['answers'] ?? []) as $answer)
            <article class="share-answer">
                <h3>{{ $answer['label'] ?? '' }}</h3>
                @if (!empty($answer['source']))
                    <p class="share-meta">{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Source' : 'Forrás' }}: {{ $answer['source'] }}</p>
                @endif
                <p>{{ $answer['text'] ?? '' }}</p>
            </article>
        @endforeach

        <div class="share-actions">
            <a href="{{ route('home') }}">{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Back to AstroTarot' : 'Vissza az AstroTarothoz' }}</a>
        </div>
    </section>
</main>
</body>
</html>

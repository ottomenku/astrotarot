<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.contact_title') }} · AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main class="app-shell">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot">
                <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
                <span class="brand-name">Astro<span>Tarot</span></span>
            </a>
            <div class="header-actions">
                <a class="credits-back" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}">{{ __('messages.legal_back') }}</a>
            </div>
        </div>
    </header>
    <article class="legal-page">
        <p class="credits-kicker">AstroTarot</p>
        <h1>{{ __('messages.contact_title') }}</h1>
        <p class="credits-lead">{{ __('messages.contact_lead') }}</p>
        <p class="contact-email">
            <a href="mailto:{{ $email }}">{{ $email }}</a>
        </p>
    </article>
    @include('templates.art-deco.partials.site-footer')
</main>
</body>
</html>

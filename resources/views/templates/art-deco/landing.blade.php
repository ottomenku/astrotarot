<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
    <link rel="stylesheet" href="{{ $assetBase }}/landing.css?v={{ filemtime(public_path('templates/art-deco/landing.css')) }}">
</head>
<body class="landing-body">
<main class="app-shell landing-shell">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot">
                <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
                <span class="brand-name">Astro<span>Tarot</span></span>
            </a>
            <div class="header-actions">
                <label class="language-select">
                    <span class="sr-only">{{ __('messages.language') }}</span>
                    <select onchange="window.location.href=this.value">
                        <option value="{{ route('locale.switch', 'hu') }}" @selected(app()->getLocale() === 'hu')>HU</option>
                        <option value="{{ route('locale.switch', 'en') }}" @selected(app()->getLocale() === 'en')>EN</option>
                    </select>
                </label>
                <button class="landing-login-link" type="button" data-auth-open="login">{{ __('messages.login') }}</button>
            </div>
        </div>
    </header>

    <section class="landing-hero">
        <div class="landing-copy">
            <p class="landing-kicker">{{ __('messages.landing_kicker') }}</p>
            <h1>{{ __('messages.landing_title') }}</h1>
            <p class="landing-lead">{{ __('messages.landing_lead') }}</p>
            <ul class="landing-points">
                <li>{{ __('messages.landing_point_sky') }}</li>
                <li>{{ __('messages.landing_point_question') }}</li>
                <li>{{ __('messages.landing_point_minutes') }}</li>
            </ul>
            <div class="landing-actions">
                @if ($registrationEnabled)
                    <button class="landing-cta landing-cta-primary" type="button" data-auth-open="register">{{ __('messages.landing_cta_register') }}</button>
                @endif
                <button class="landing-cta {{ $registrationEnabled ? 'landing-cta-secondary' : 'landing-cta-primary' }}" type="button" data-auth-open="login">{{ __('messages.landing_cta_login') }}</button>
            </div>
            @unless ($registrationEnabled)
                <p class="landing-closed">{{ __('messages.landing_cta_closed') }}</p>
            @endunless
        </div>
        <figure class="landing-figure">
            <img
                src="{{ $assetBase }}/landing-tarot.jpg"
                alt="{{ __('messages.landing_image_alt') }}"
                width="1200"
                height="900"
            >
        </figure>
    </section>

    <footer><span>✦</span> AstroTarot <i>·</i> Égi Orákulum + Tarot <span>✦</span></footer>
</main>
@include('templates.art-deco.partials.auth-modal', [
    'registrationEnabled' => $registrationEnabled ?? true,
    'authOpen' => $errors->any(),
    'authStartTab' => old('name') ? 'register' : 'login',
])
<script src="{{ $assetBase }}/auth-modal.js?v={{ filemtime(public_path('templates/art-deco/auth-modal.js')) }}" defer></script>
</body>
</html>

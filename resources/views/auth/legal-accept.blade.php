<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.legal_accept_title') }} · AstroTarot</title>
    @php($assetBase = $assetBase ?? asset('templates/art-deco'))
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
        </div>
    </header>
    <div class="auth-modal" data-auth-modal data-auth-home="{{ route('home') }}">
        <div class="auth-modal-backdrop"></div>
        <div class="auth-modal-panel" role="dialog" aria-modal="true" aria-labelledby="legal-accept-title">
            <a class="auth-modal-close" href="{{ route('home') }}" aria-label="Bezárás">×</a>
            <p class="auth-modal-kicker">AstroTarot</p>
            <h2 id="legal-accept-title">{{ __('messages.legal_accept_title') }}</h2>
            <p class="auth-modal-lead">{{ __('messages.legal_accept_lead') }}</p>
            @if ($errors->any())
                <div class="auth-modal-error">{{ $errors->first() }}</div>
            @endif
            <form class="auth-form is-active" method="POST" action="{{ route('register.legal.store') }}">
                @csrf
                <div class="auth-legal">
                    <label class="auth-check">
                        <input type="checkbox" name="privacy" value="1" required>
                        <span>{!! __('messages.legal_accept_privacy', ['url' => route('legal.privacy')]) !!}</span>
                    </label>
                    <label class="auth-check">
                        <input type="checkbox" name="terms" value="1" required>
                        <span>{!! __('messages.legal_accept_terms', ['url' => route('legal.terms')]) !!}</span>
                    </label>
                </div>
                <button type="submit" class="auth-submit">{{ __('messages.register_submit') }}</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>

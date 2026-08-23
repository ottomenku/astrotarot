<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.login') }} · AstroTarot</title>
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
    @include('templates.art-deco.partials.auth-modal', [
        'registrationEnabled' => $registrationEnabled ?? true,
        'authOpen' => true,
        'authStartTab' => $authStartTab ?? 'login',
        'authCloseToHome' => true,
    ])
</main>
<script src="{{ $assetBase }}/auth-modal.js?v={{ filemtime(public_path('templates/art-deco/auth-modal.js')) }}" defer></script>
</body>
</html>

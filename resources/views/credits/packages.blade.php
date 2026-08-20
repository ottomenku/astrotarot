<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.credits_packages_title') }} · AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main class="app-shell credits-page">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot kezdőlap">
            <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
            <span class="brand-name">Astro<span>Tarot</span></span>
        </a>
        <div class="header-actions">
            <a class="credits-back" href="{{ route('home') }}">{{ __('messages.credits_back_home') }}</a>
        </div>
    </header>

    <section class="credits-panel">
        <p class="credits-kicker">{{ __('messages.credits_label') }}</p>
        <h1>{{ __('messages.credits_packages_title') }}</h1>
        <p class="credits-lead">{{ __('messages.credits_packages_lead') }}</p>

        <p class="credits-balance-line">
            {{ __('messages.credits_your_balance') }}:
            <strong>{{ __('messages.credits_balance', ['count' => $balance]) }}</strong>
        </p>

        @if (session('status'))
            <div class="credits-flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="credits-error">{{ $errors->first() }}</div>
        @endif

        <div class="credits-packages">
            @foreach ($packages as $package)
                @php
                    $label = $locale === 'en' ? $package['label_en'] : $package['label_hu'];
                @endphp
                <form class="credits-package" method="POST" action="{{ route('credits.request') }}">
                    @csrf
                    <input type="hidden" name="package_key" value="{{ $package['key'] }}">
                    <div>
                        <h2>{{ $label }}</h2>
                        <p>{{ __('messages.credits_balance', ['count' => $package['credits']]) }}</p>
                    </div>
                    <label class="credits-note">
                        <span>{{ __('messages.credits_note_label') }}</span>
                        <textarea name="note" rows="2" maxlength="500" placeholder="…"></textarea>
                    </label>
                    <button type="submit">{{ __('messages.credits_request') }}</button>
                </form>
            @endforeach
        </div>
    </section>

    <footer><span>✦</span> AstroTarot <i>·</i> Égi Orákulum + Tarot <span>✦</span></footer>
</main>
</body>
</html>

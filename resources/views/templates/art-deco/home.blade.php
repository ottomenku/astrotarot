<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main
    class="app-shell"
    id="astrotarot-app"
    data-asset-base="{{ $assetBase }}"
    data-locale="{{ app()->getLocale() }}"
    data-locale-switch-url="{{ url('/locale/__LOCALE__') }}"
    data-reading-url="{{ route('reading') }}"
    data-min-question-chars="{{ $minQuestionChars }}"
    data-llm-enabled="{{ !empty($llmEnabled) ? '1' : '0' }}"
    data-draw-profile='@json($drawProfile)'
>
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot kezdőlap">
            <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
            <span class="brand-name">Astro<span>Tarot</span></span>
        </a>
        <div class="header-actions">
            <label class="language-select">
                <span class="sr-only">Language</span>
                <select data-language>
                    <option value="hu" @selected(app()->getLocale() === 'hu')>HU</option>
                    <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
                </select>
            </label>
            <button class="menu-button" type="button" data-menu-open aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <div class="menu-backdrop" data-menu-backdrop></div>
    <aside class="side-menu" data-side-menu aria-hidden="true">
        <div class="menu-top">
            <span>AstroTarot</span>
            <button type="button" data-menu-close aria-label="Bezárás">×</button>
        </div>
        <nav>
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.ephemeris.index') }}"><span>01</span>Admin<b>↗</b></a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><span>02</span>{{ __('messages.logout') }}<b>↗</b></button>
                </form>
            @else
                <a href="{{ route('login') }}"><span>01</span>{{ __('messages.login') }}<b>↗</b></a>
            @endauth
        </nav>
        <p>Égi Art Deco · 22 lapos nagy arkánum</p>
    </aside>

    <section class="deck-section" aria-labelledby="deck-heading">
        <div class="compact-question">
            <h1 id="deck-heading" data-title></h1>
            <label class="question-field">
                <textarea data-question rows="2" maxlength="280"></textarea>
                <span>0/280</span>
            </label>
            <div class="question-meta">
                <p class="field-hint" data-hint></p>
                <span class="selection-count" data-counter></span>
            </div>
        </div>

        <div class="deck-wrap">
            <button class="deck-arrow left" type="button" data-scroll-left aria-label="Balra görgetés">‹</button>
            <div class="deck-slider" data-deck-slider role="list"></div>
            <button class="deck-arrow right" type="button" data-scroll-right aria-label="Jobbra görgetés">›</button>
        </div>
    </section>

    <section class="reading-panel">
        <div class="reveal-heading">
            <button class="reset-button" type="button" data-reset hidden></button>
        </div>

        <div class="chosen-grid" data-chosen-grid aria-live="polite"></div>
    </section>

    <div class="reading-modal" data-reading-modal hidden>
        <div class="reading-modal-backdrop" data-reading-close></div>
        <div class="reading-modal-panel" role="dialog" aria-modal="true" aria-labelledby="reading-modal-title">
            <button class="reading-modal-close" type="button" data-reading-close aria-label="Bezárás">×</button>
            <p class="reading-modal-kicker" data-reading-kicker></p>
            <h2 id="reading-modal-title" data-reading-title></h2>
            <p class="reading-modal-question" data-reading-question></p>
            <div class="reading-modal-body" data-reading-body></div>
        </div>
    </div>

    <footer><span>✦</span> AstroTarot <i>·</i> Égi Art Deco <span>✦</span></footer>
</main>

<script src="{{ $assetBase }}/app.js?v={{ filemtime(public_path('templates/art-deco/app.js')) }}" defer></script>
</body>
</html>

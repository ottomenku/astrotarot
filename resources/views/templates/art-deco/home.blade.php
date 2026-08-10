<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css">
</head>
<body>
<main
    class="app-shell"
    id="astrotarot-app"
    data-asset-base="{{ $assetBase }}"
    data-locale="{{ app()->getLocale() }}"
    data-locale-switch-url="{{ url('/locale/__LOCALE__') }}"
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
        <div class="swipe-hint" aria-hidden="true"><span>←</span><i></i><span>→</span></div>
    </section>

    <section class="reading-panel">
        <div class="reveal-heading">
            <button class="reset-button" type="button" data-reset hidden></button>
        </div>

        <div class="chosen-grid" data-chosen-grid aria-live="polite"></div>

        <button class="reveal-button" type="button" data-reveal disabled>
            <span></span><b>✦</b>
        </button>

        <div class="message-card" data-message role="status">
            <span class="message-symbol">☼</span>
            <div>
                <p data-message-title></p>
                <h2 data-message-question></h2>
                <blockquote data-message-body></blockquote>
            </div>
        </div>
    </section>

    <footer><span>✦</span> AstroTarot <i>·</i> Égi Art Deco <span>✦</span></footer>
</main>

<script src="{{ $assetBase }}/app.js" defer></script>
</body>
</html>

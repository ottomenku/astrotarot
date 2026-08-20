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
    data-authenticated="{{ auth()->check() ? '1' : '0' }}"
    data-open-auth="{{ (!auth()->check() && $errors->any()) ? '1' : '0' }}"
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
                <button type="button" data-auth-open><span>01</span>{{ __('messages.login') }}<b>↗</b></button>
            @endauth
        </nav>
        <p>Égi Orákulum + Tarot</p>
    </aside>

    <section class="deck-section" data-pick-stage aria-labelledby="deck-heading">
        <div class="compact-question">
            <h1 id="deck-heading" data-title></h1>
            <label class="question-field {{ auth()->check() ? '' : 'is-guest-gate' }}">
                <textarea
                    data-question
                    rows="2"
                    maxlength="280"
                    @unless(auth()->check())
                        readonly
                        data-guest-gate
                    @endunless
                ></textarea>
                <span @unless(auth()->check()) hidden @endunless>0/280</span>
            </label>
            <div class="question-meta">
                <p class="field-hint" data-hint></p>
                <span class="selection-count" data-counter></span>
            </div>
        </div>

        <p class="deck-phase-label" data-deck-label></p>
        <div class="deck-wrap" data-deck-wrap>
            <button class="deck-arrow left" type="button" data-scroll-left aria-label="Balra görgetés">‹</button>
            <div class="deck-slider" data-deck-slider role="list"></div>
            <button class="deck-arrow right" type="button" data-scroll-right aria-label="Jobbra görgetés">›</button>
        </div>
    </section>

    <section class="reading-panel">
        <div class="result-header" data-result-header hidden>
            <p class="asked-question" data-asked-question></p>
            <button class="reset-button" type="button" data-reset></button>
        </div>

        <div class="chosen-grid chosen-grid-two" data-chosen-grid aria-live="polite"></div>

        <article class="reading-inline" data-reading-result hidden>
            <p class="reading-inline-kicker" data-reading-kicker></p>
            <h2 class="reading-inline-title" data-reading-title></h2>
            <div class="reading-inline-body" data-reading-body></div>
            <div class="reading-followup" data-reading-followup hidden>
                <label class="reading-followup-label" for="reading-followup-input" data-reading-followup-label>Pontosítást kérek…</label>
                <textarea id="reading-followup-input" class="reading-followup-input" data-reading-followup-input rows="3" maxlength="500" placeholder="Pontosítást kérek…"></textarea>
                <div class="reading-followup-actions">
                    <button type="button" class="reading-followup-send" data-reading-followup-send>Elküld</button>
                    <button type="button" class="reading-followup-expand" data-reading-followup-expand>Bővebb kifejtést kérek</button>
                </div>
            </div>
        </article>
    </section>

    <div class="reading-toast" data-reading-toast hidden>
        <span class="reading-toast-dot" aria-hidden="true"></span>
        <span data-reading-toast-text></span>
        <button type="button" class="reading-toast-close" data-reading-toast-close aria-label="Bezárás">×</button>
    </div>

    @guest
        @php
            $googleReady = filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
            $facebookReady = filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret'));
        @endphp
        <div class="auth-modal" data-auth-modal hidden>
            <div class="auth-modal-backdrop" data-auth-close></div>
            <div class="auth-modal-panel" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">
                <button class="auth-modal-close" type="button" data-auth-close aria-label="Bezárás">×</button>
                <p class="auth-modal-kicker">AstroTarot</p>
                <h2 id="auth-modal-title">{{ __('messages.auth_modal_title') }}</h2>
                <p class="auth-modal-lead">{{ __('messages.auth_modal_lead') }}</p>

                @if ($errors->any())
                    <div class="auth-modal-error">{{ $errors->first() }}</div>
                @endif

                <div class="auth-social">
                    <a
                        class="auth-social-btn auth-social-google {{ $googleReady ? '' : 'is-disabled' }}"
                        href="{{ $googleReady ? route('auth.social.redirect', 'google') : '#' }}"
                        @unless($googleReady) aria-disabled="true" tabindex="-1" @endunless
                    >
                        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.3 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.6 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.1 5.6l.1.1 6.2 5.2C39.2 36.3 44 32 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg>
                        <span>{{ __('messages.login_google') }}</span>
                    </a>
                    <a
                        class="auth-social-btn auth-social-facebook {{ $facebookReady ? '' : 'is-disabled' }}"
                        href="{{ $facebookReady ? route('auth.social.redirect', 'facebook') : '#' }}"
                        @unless($facebookReady) aria-disabled="true" tabindex="-1" @endunless
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.03H7.9v-2.9h2.4V9.84c0-2.37 1.41-3.68 3.57-3.68 1.03 0 2.12.18 2.12.18v2.33h-1.2c-1.18 0-1.55.73-1.55 1.48v1.78h2.64l-.42 2.9h-2.22V22c4.78-.75 8.44-4.91 8.44-9.93z"/></svg>
                        <span>{{ __('messages.login_facebook') }}</span>
                    </a>
                </div>
                @unless($googleReady || $facebookReady)
                    <p class="auth-or">{{ __('messages.social_unavailable') }}</p>
                @endunless
                <p class="auth-or">{{ __('messages.login_or_email') }}</p>

                <div class="auth-tabs" role="tablist">
                    <button type="button" class="auth-tab is-active" data-auth-tab="login" role="tab" aria-selected="true">{{ __('messages.login') }}</button>
                    <button type="button" class="auth-tab" data-auth-tab="register" role="tab" aria-selected="false">{{ __('messages.register') }}</button>
                </div>

                <form class="auth-form is-active" data-auth-panel="login" method="POST" action="{{ route('login') }}">
                    @csrf
                    <label class="auth-field">
                        <span>{{ __('admin.users.email') }}</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    </label>
                    <label class="auth-field">
                        <span>{{ __('admin.users.password') }}</span>
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    <label class="auth-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>{{ __('messages.remember') }}</span>
                    </label>
                    <button type="submit" class="auth-submit">{{ __('messages.login') }}</button>
                </form>

                <form class="auth-form" data-auth-panel="register" method="POST" action="{{ route('register') }}" hidden>
                    @csrf
                    <label class="auth-field">
                        <span>{{ __('messages.name') }}</span>
                        <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                    </label>
                    <label class="auth-field">
                        <span>{{ __('admin.users.email') }}</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    </label>
                    <label class="auth-field">
                        <span>{{ __('admin.users.password') }}</span>
                        <input type="password" name="password" required autocomplete="new-password">
                    </label>
                    <label class="auth-field">
                        <span>{{ __('messages.password_confirm') }}</span>
                        <input type="password" name="password_confirmation" required autocomplete="new-password">
                    </label>
                    <button type="submit" class="auth-submit">{{ __('messages.register_submit') }}</button>
                </form>
            </div>
        </div>
    @endguest

    <footer><span>✦</span> AstroTarot <i>·</i> Égi Orákulum + Tarot <span>✦</span></footer>
</main>

<script src="{{ $assetBase }}/app.js?v={{ filemtime(public_path('templates/art-deco/app.js')) }}" defer></script>
</body>
</html>

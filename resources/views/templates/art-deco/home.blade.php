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
    data-pack-base="{{ $packBase ?? asset('card-pack') }}"
    data-game='@json($gameConfig ?? [])'
    data-locale="{{ app()->getLocale() }}"
    data-locale-switch-url="/locale/__LOCALE__"
    data-reading-url="{{ route('reading', absolute: false) }}"
    data-export-pdf-url="{{ auth()->check() ? route('reading.export.pdf', absolute: false) : '' }}"
    data-export-email-url="{{ auth()->check() ? route('reading.export.email', absolute: false) : '' }}"
    data-share-url="{{ auth()->check() ? route('reading.share.create', absolute: false) : '' }}"
    data-user-email="{{ auth()->check() ? (auth()->user()->email ?? '') : '' }}"
    data-feature-access='@json($featureAccess ?? [])'
    data-credits-packages-url="{{ route('credits.packages') }}"
    data-min-question-chars="{{ $minQuestionChars }}"
    data-llm-enabled="{{ !empty($llmEnabled) ? '1' : '0' }}"
    data-authenticated="{{ auth()->check() ? '1' : '0' }}"
    data-credits="{{ auth()->check() ? (int) auth()->user()->credits : 0 }}"
    data-open-auth="{{ (!auth()->check() && $errors->any()) ? '1' : '0' }}"
    data-draw-profile='@json($drawProfile)'
>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot kezdőlap">
                <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
                <span class="brand-name">Astro<span>Tarot</span></span>
            </a>
            <div class="header-actions">
                @auth
                    @if ($paidFeaturesEnabled)
                    <span class="feature-control">
                        @if (!empty($canOpenCredits))
                            <a
                                class="credits-chip"
                                href="{{ route('credits.packages') }}"
                                title="{{ __('messages.credits_label') }}"
                                aria-label="{{ __('messages.credits_balance', ['count' => (int) auth()->user()->credits]) }}"
                            >
                                <svg class="credits-chip-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="9.25" fill="none" stroke="currentColor" stroke-width="1.4"/>
                                    <circle cx="12" cy="12" r="6.4" fill="none" stroke="currentColor" stroke-width="1.1" opacity=".7"/>
                                    <text x="12" y="12.6" text-anchor="middle" dominant-baseline="middle" font-size="8.5" font-family="Georgia, serif" fill="currentColor">✦</text>
                                </svg>
                                <span data-credits-balance>{{ (int) auth()->user()->credits }}</span>
                            </a>
                        @else
                            <span
                                class="credits-chip"
                                title="{{ __('messages.credits_label') }}"
                                aria-label="{{ __('messages.credits_balance', ['count' => (int) auth()->user()->credits]) }}"
                            >
                                <svg class="credits-chip-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="9.25" fill="none" stroke="currentColor" stroke-width="1.4"/>
                                    <circle cx="12" cy="12" r="6.4" fill="none" stroke="currentColor" stroke-width="1.1" opacity=".7"/>
                                    <text x="12" y="12.6" text-anchor="middle" dominant-baseline="middle" font-size="8.5" font-family="Georgia, serif" fill="currentColor">✦</text>
                                </svg>
                                <span data-credits-balance>{{ (int) auth()->user()->credits }}</span>
                            </span>
                        @endif
                        <x-feature-marks feature="credits.packages" />
                    </span>
                    @endif
                @endauth
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
                <p class="menu-credits" data-credits-balance-wrap>
                    {{ __('messages.credits_label') }}:
                    <strong data-credits-balance>{{ (int) auth()->user()->credits }}</strong>
                </p>
                @php $menuIndex = 1; @endphp
                <a href="{{ route('blog.index') }}"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span>{{ __('messages.blog_title') }}<b>↗</b></a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.ephemeris.index') }}"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span>Admin<b>↗</b></a>
                @endif
                @if(!empty($canOpenCredits) && $paidFeaturesEnabled)
                    <a href="{{ route('credits.packages') }}"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span><span class="menu-link-text">{{ __('messages.credits_menu') }}<x-feature-marks feature="credits.packages" /></span><b>↗</b></a>
                @endif
                @if(!empty($canOpenAccount))
                    <a href="{{ route('account.show') }}"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span><span class="menu-link-text">{{ __('messages.account_menu') }}<x-feature-marks feature="account.show" /></span><b>↗</b></a>
                    <a href="{{ route('account.introduction') }}"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span><span class="menu-link-text">{{ __('messages.intro_menu') }}<x-feature-marks feature="account.show" /></span><b>↗</b></a>
                @endif
                @if(!empty($canViewHistory))
                    <a href="{{ route('readings.history') }}"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span><span class="menu-link-text">{{ __('messages.history_menu') }}<x-feature-marks feature="readings.history" /></span><b>↗</b></a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><span>{{ str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) }}</span>{{ __('messages.logout') }}<b>↗</b></button>
                </form>
            @else
                <a href="{{ route('blog.index') }}"><span>01</span>{{ __('messages.blog_title') }}<b>↗</b></a>
                <button type="button" data-auth-open><span>02</span>{{ __('messages.login') }}<b>↗</b></button>
            @endauth
        </nav>
        <p>Égi Orákulum + Tarot</p>
    </aside>

    @if (session('credits_welcome') && $paidFeaturesEnabled)
        <div class="credits-welcome" data-credits-welcome role="status">
            <button type="button" class="credits-welcome-close" data-credits-welcome-close aria-label="Bezárás">×</button>
            <p class="credits-welcome-kicker">AstroTarot</p>
            <h2>{{ __('messages.credits_welcome_title') }}</h2>
            <p>{{ __('messages.credits_welcome_body') }}</p>
            <a class="credits-welcome-link" href="{{ route('credits.packages') }}">{{ __('messages.credits_menu') }}<x-feature-marks feature="credits.packages" /></a>
        </div>
    @endif

    <section class="deck-section" data-pick-stage aria-labelledby="deck-heading">
        <div class="compact-question">
            @php
                $spreadChoices = $gameConfig['choices'] ?? [];
                $spreadCount = count($gameConfig['slots'] ?? []);
                $spreadName = $gameConfig['name'] ?? '';
            @endphp
            <div class="spread-bar">
                <h1 id="deck-heading" class="game-description" data-game-description>
                    <span class="spread-kicker" data-spread-kicker>{{ __('messages.spread_current') }}</span>
                    <span class="spread-summary" data-spread-summary>{{ __('messages.spread_summary', ['name' => $spreadName, 'count' => $spreadCount]) }}</span>
                </h1>
                <button class="spread-change" type="button" data-spread-open>{{ __('messages.spread_change') }}</button>
            </div>
            <form class="spread-panel" data-spread-panel method="get" action="{{ route('home') }}" hidden>
                <input type="hidden" name="spread" value="1">
                @guest
                    <input type="hidden" name="draw" value="1">
                @endguest
                <label>
                    <span data-copy="spreadGame">{{ __('messages.spread_game') }}</span>
                    <select name="game" data-spread-game>
                        @foreach ($spreadChoices['games'] ?? [] as $choice)
                            <option
                                value="{{ $choice['id'] }}"
                                data-types="{{ implode(',', $choice['types'] ?? []) }}"
                                data-name-hu="{{ $choice['name_hu'] }}"
                                data-name-en="{{ $choice['name_en'] }}"
                                @selected((int) ($spreadChoices['selected_game'] ?? 0) === (int) $choice['id'])
                            >{{ app()->getLocale() === 'en' ? $choice['name_en'] : $choice['name_hu'] }}</option>
                        @endforeach
                    </select>
                </label>
                @foreach (\App\Models\CardPack::FIGURE_TYPES as $packType)
                    <label data-pack-type="{{ $packType }}">
                        <span><span data-copy="spreadPack">{{ __('messages.spread_pack') }}</span> · {{ __('admin.packs.type_'.$packType) }}</span>
                        <select name="pack_{{ $packType }}">
                            <option value="" data-name-hu="{{ __('messages.spread_default', [], 'hu') }}" data-name-en="{{ __('messages.spread_default', [], 'en') }}">{{ __('messages.spread_default') }}</option>
                            @foreach ($gameConfig['pack_options'][$packType] ?? [] as $pack)
                                <option
                                    value="{{ $pack['slug'] }}"
                                    data-name-hu="{{ $pack['name_hu'] ?? $pack['name'] }}"
                                    data-name-en="{{ $pack['name_en'] ?? $pack['name'] }}"
                                    @selected(($spreadChoices['selected_packs'][$packType] ?? '') === $pack['slug'])
                                >{{ $pack['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <label>
                    <span data-copy="spreadBack">{{ __('messages.spread_back') }}</span>
                    <select name="back" data-spread-back>
                        <option value="" data-preview="" data-name-hu="{{ __('messages.spread_default', [], 'hu') }}" data-name-en="{{ __('messages.spread_default', [], 'en') }}">{{ __('messages.spread_default') }}</option>
                        @foreach ($spreadChoices['backs'] ?? [] as $back)
                            <option
                                value="{{ $back['id'] }}"
                                data-preview="{{ $back['preview'] }}"
                                data-name-hu="{{ $back['name_hu'] }}"
                                data-name-en="{{ $back['name_en'] }}"
                                @selected((int) ($spreadChoices['selected_back'] ?? 0) === (int) $back['id'])
                            >{{ app()->getLocale() === 'en' ? $back['name_en'] : $back['name_hu'] }}</option>
                        @endforeach
                    </select>
                </label>
                <img class="spread-back-preview" data-spread-back-preview alt="" hidden>
                <div class="spread-panel-actions">
                    <button type="submit" data-copy="spreadApply">{{ __('messages.spread_apply') }}</button>
                    <button type="button" data-spread-close data-copy="spreadClose">{{ __('messages.spread_close') }}</button>
                </div>
            </form>
            <label
                class="question-field"
                data-question-wrap
                @if (empty($gameConfig['has_question'])) hidden @endif
            >
                <textarea data-question rows="2" maxlength="280"></textarea>
                <span>0/280</span>
            </label>
            <div class="question-meta">
                <p class="field-hint" data-hint></p>
                <x-feature-marks feature="reading.initial" />
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
            <button class="reading-followup-reset" type="button" data-reset>Új húzás</button>
        </div>

        <div class="chosen-grid" data-chosen-grid aria-live="polite"></div>

        <article class="reading-inline" data-reading-result hidden>
            <p class="reading-inline-kicker" data-reading-kicker></p>
            <h2 class="reading-inline-title" data-reading-title></h2>
            <div class="reading-inline-body" data-reading-body></div>
            <p class="reading-inline-source" data-reading-source hidden></p>
            <div class="reading-followup" data-reading-followup hidden>
                <label class="reading-followup-label" for="reading-followup-input" data-reading-followup-label>Pontosítást kérek…</label>
                <textarea id="reading-followup-input" class="reading-followup-input" data-reading-followup-input rows="3" maxlength="500" placeholder="Pontosítást kérek…"></textarea>
                <div class="reading-followup-actions">
                    <x-feature-control feature="reading.clarify">
                        <button type="button" class="reading-followup-send" data-reading-followup-send>Elküld</button>
                    </x-feature-control>
                    <x-feature-control feature="reading.expand">
                        <button type="button" class="reading-followup-expand" data-reading-followup-expand>Bővebb kifejtést kérek</button>
                    </x-feature-control>
                    <button type="button" class="reading-followup-reset" data-reset>Új húzás</button>
                </div>
            </div>
            <div class="reading-share" data-reading-share hidden>
                <p class="reading-share-label" data-reading-share-label>{{ __('messages.share_label') }}</p>
                <div class="reading-share-actions">
                    <x-feature-control class="feature-control-icon" feature="reading.export.pdf">
                        <button type="button" class="reading-share-btn reading-share-icon" data-share-pdf title="{{ __('messages.share_pdf') }}" aria-label="{{ __('messages.share_pdf') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm1 7V3.5L18.5 9H15zM8 13h2.5a1.5 1.5 0 0 1 0 3H9v2H8v-5zm1 1v1.5h1.5a.5.5 0 0 0 0-1H9zm4.5 0H14a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-.5.5h-.5a.5.5 0 0 1-.5-.5v-3a.5.5 0 0 1 .5-.5zm0 1v2h.5v-2h-.5zM16 13h3v1h-2v1h1.5v1H17v1h-1v-4z"/></svg>
                        </button>
                    </x-feature-control>
                    <x-feature-control class="feature-control-icon" feature="reading.export.email">
                        <button type="button" class="reading-share-btn reading-share-icon" data-share-email title="{{ __('messages.share_email') }}" aria-label="{{ __('messages.share_email') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z"/></svg>
                        </button>
                    </x-feature-control>
                    <x-feature-control class="feature-control-icon" feature="reading.share">
                        <button type="button" class="reading-share-btn reading-share-icon reading-share-fb" data-share-facebook title="{{ __('messages.share_facebook') }}" aria-label="{{ __('messages.share_facebook') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.03H7.9v-2.9h2.4V9.84c0-2.37 1.41-3.68 3.57-3.68 1.03 0 2.12.18 2.12.18v2.33h-1.2c-1.18 0-1.55.73-1.55 1.48v1.78h2.64l-.42 2.9h-2.22V22c4.78-.75 8.44-4.91 8.44-9.93z"/></svg>
                        </button>
                    </x-feature-control>
                </div>
            </div>
        </article>
    </section>

    <div class="draw-reveal" data-draw-reveal hidden>
        <figure class="draw-reveal-card">
            <img data-draw-reveal-image alt="">
        </figure>
    </div>

    <div class="reading-toast" data-reading-toast hidden>
        <span class="reading-toast-dot" aria-hidden="true"></span>
        <span data-reading-toast-text></span>
        <button type="button" class="reading-toast-close" data-reading-toast-close aria-label="Bezárás">×</button>
    </div>

    @guest
        @include('templates.art-deco.partials.auth-modal', [
            'registrationEnabled' => $registrationEnabled ?? true,
            'authOpen' => $errors->any(),
            'authStartTab' => old('name') ? 'register' : 'login',
        ])
    @endguest

    @include('templates.art-deco.partials.site-footer')
</main>

<script src="{{ $assetBase }}/app.js?v={{ filemtime(public_path('templates/art-deco/app.js')) }}" defer></script>
</body>
</html>

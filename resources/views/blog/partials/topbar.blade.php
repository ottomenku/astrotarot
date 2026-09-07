<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot">
            <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
            <span class="brand-name">Astro<span>Tarot</span></span>
        </a>
        <div class="header-actions">
            <a class="credits-back" href="{{ route('blog.index') }}" @class(['is-current' => request()->routeIs('blog.*')])>{{ __('messages.blog_title') }}</a>
            <label class="language-select">
                <span class="sr-only">{{ __('messages.language') }}</span>
                <select onchange="window.location.href=this.value">
                    <option value="{{ route('locale.switch', 'hu') }}" @selected(app()->getLocale() === 'hu')>HU</option>
                    <option value="{{ route('locale.switch', 'en') }}" @selected(app()->getLocale() === 'en')>EN</option>
                </select>
            </label>
            @auth
                <a class="credits-back" href="{{ route('home') }}">{{ __('messages.credits_back_home') }}</a>
            @else
                <a class="credits-back" href="{{ route('login') }}">{{ __('messages.login') }}</a>
            @endauth
        </div>
    </div>
</header>

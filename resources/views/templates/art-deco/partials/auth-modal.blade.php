@php
    $googleReady = filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    $facebookReady = filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret'));
    $registrationEnabled = $registrationEnabled ?? true;
    $authOpen = $authOpen ?? false;
    $authStartTab = ($registrationEnabled && ($authStartTab ?? 'login') === 'register') ? 'register' : 'login';
    $authCloseToHome = $authCloseToHome ?? false;
@endphp
<div
    class="auth-modal"
    data-auth-modal
    data-auth-start="{{ $authStartTab }}"
    @if ($authCloseToHome) data-auth-home="{{ route('home') }}" @endif
    @unless($authOpen) hidden @endunless
>
    <div class="auth-modal-backdrop" data-auth-close></div>
    <div class="auth-modal-panel" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">
        @if ($authCloseToHome)
            <a class="auth-modal-close" href="{{ route('home') }}" aria-label="Bezárás">×</a>
        @else
            <button class="auth-modal-close" type="button" data-auth-close aria-label="Bezárás">×</button>
        @endif
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
            <button type="button" class="auth-tab {{ $authStartTab === 'login' ? 'is-active' : '' }}" data-auth-tab="login" role="tab" aria-selected="{{ $authStartTab === 'login' ? 'true' : 'false' }}">{{ __('messages.login') }}</button>
            @if ($registrationEnabled)
                <button type="button" class="auth-tab {{ $authStartTab === 'register' ? 'is-active' : '' }}" data-auth-tab="register" role="tab" aria-selected="{{ $authStartTab === 'register' ? 'true' : 'false' }}">{{ __('messages.register') }}</button>
            @endif
        </div>

        <form class="auth-form {{ $authStartTab === 'login' ? 'is-active' : '' }}" data-auth-panel="login" method="POST" action="{{ route('login') }}" @unless($authStartTab === 'login') hidden @endunless>
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

        @if ($registrationEnabled)
            <form class="auth-form {{ $authStartTab === 'register' ? 'is-active' : '' }}" data-auth-panel="register" method="POST" action="{{ route('register') }}" @unless($authStartTab === 'register') hidden @endunless>
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
                <div class="auth-legal">
                    <label class="auth-check">
                        <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
                        <span>{!! __('messages.legal_accept_privacy', ['url' => route('legal.privacy')]) !!}</span>
                    </label>
                    <label class="auth-check">
                        <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                        <span>{!! __('messages.legal_accept_terms', ['url' => route('legal.terms')]) !!}</span>
                    </label>
                </div>
                <button type="submit" class="auth-submit">{{ __('messages.register_submit') }}</button>
            </form>
        @else
            <p class="auth-or">{{ __('messages.registration_closed') }}</p>
        @endif
    </div>
</div>

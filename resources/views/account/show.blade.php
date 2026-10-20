<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.account_title') }} · AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main class="app-shell credits-page">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
                <span class="brand-name">Astro<span>Tarot</span></span>
            </a>
            <div class="header-actions">
                <a class="credits-back" href="{{ route('home') }}">{{ __('messages.credits_back_home') }}</a>
            </div>
        </div>
    </header>

    <section class="credits-panel">
        <p class="credits-kicker">{{ __('messages.account_menu') }}<x-feature-marks feature="account.show" /></p>
        <h1>{{ __('messages.account_title') }}</h1>
        <p class="credits-lead">{{ __('messages.account_lead') }}</p>

        @if (session('status'))
            <div class="credits-flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="credits-error">{{ $errors->first() }}</div>
        @endif

        <h2 class="credits-section-title">{{ __('messages.account_profile') }}</h2>
        <p class="credits-lead"><a class="credits-back" href="{{ route('account.introduction') }}">{{ __('messages.intro_menu') }}</a></p>
        <dl class="account-dl">
            <div>
                <dt>{{ __('messages.account_field_name') }}</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_email') }}</dt>
                <dd>{{ $user->email }}</dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_login') }}</dt>
                <dd>
                    @if ($user->provider === 'google')
                        {{ __('messages.account_login_google') }}
                    @elseif ($user->provider === 'facebook')
                        {{ __('messages.account_login_facebook') }}
                    @else
                        {{ __('messages.account_login_email') }}
                    @endif
                    <div class="account-inline-actions">
                        @if ($user->usesPasswordLogin())
                            <details class="account-fold">
                                <summary class="credits-back">{{ __('messages.account_password_change') }}</summary>
                                <form method="POST" action="{{ route('account.password.update') }}">
                                    @csrf
                                    @method('PUT')
                                    <label class="credits-note">
                                        <span>{{ __('messages.account_password_current') }}</span>
                                        <input type="password" name="current_password" required autocomplete="current-password">
                                    </label>
                                    <label class="credits-note">
                                        <span>{{ __('messages.account_password_new') }}</span>
                                        <input type="password" name="password" required autocomplete="new-password">
                                    </label>
                                    <label class="credits-note">
                                        <span>{{ __('messages.password_confirm') }}</span>
                                        <input type="password" name="password_confirmation" required autocomplete="new-password">
                                    </label>
                                    <button type="submit" class="credits-back">{{ __('messages.account_password_change') }}</button>
                                </form>
                            </details>
                        @endif
                        @if ($pendingDeletion)
                            <p class="credits-lead">{{ __('messages.account_erasure_pending') }}</p>
                        @else
                            <details class="account-fold">
                                <summary class="credits-danger-btn">{{ __('messages.account_delete_account') }}</summary>
                                <form
                                    method="POST"
                                    action="{{ route('account.deletion-request.store') }}"
                                    data-confirm="{{ __('messages.account_erasure_confirm') }}"
                                >
                                    @csrf
                                    <p class="credits-lead">{{ __('messages.account_erasure_lead') }}</p>
                                    <label class="credits-note">
                                        <span>{{ __('messages.account_erasure_note') }}</span>
                                        <textarea name="note" rows="2" maxlength="500"></textarea>
                                    </label>
                                    <button type="submit" class="credits-danger-btn">{{ __('messages.account_erasure_submit') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </dd>
            </div>
            @if ($paidFeaturesEnabled)
            <div>
                <dt>{{ __('messages.account_field_plan') }}</dt>
                <dd>
                    <span class="account-inline">
                        <span>{{ strtoupper($user->planKey()) }}</span>
                        <a class="credits-back" href="{{ route('credits.packages') }}#plans">{{ __('messages.account_choose_plan') }}</a>
                    </span>
                </dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_credits') }}</dt>
                <dd>
                    <span class="account-inline">
                        <span>{{ __('messages.credits_balance', ['count' => (int) $user->credits]) }}</span>
                        <a class="credits-back" href="{{ route('credits.packages') }}#credits">{{ __('messages.account_buy_credits') }}</a>
                    </span>
                </dd>
            </div>
            @endif
        </dl>

        <h2 class="credits-section-title">{{ __('messages.account_packs') }}<x-feature-marks feature="packs.choose" /></h2>
        <p class="credits-lead">{{ $paidFeaturesEnabled ? __('messages.account_packs_lead') : __('messages.account_packs_lead_open') }}</p>
        @unless ($canChoosePacks || $canChooseBacks)
            <p class="credits-lead">{{ __('messages.account_packs_locked') }}</p>
        @endunless

        <form class="account-packs" method="POST" action="{{ route('account.packs.update') }}">
            @csrf

            @foreach ($figureOptions as $type => $options)
                <label class="credits-note">
                    <span>{{ __('messages.account_packs_figure') }} · {{ __('admin.packs.type_'.$type) }}<x-feature-marks feature="packs.choose" /></span>
                    <select name="figures[{{ $type }}]" @disabled(! $canChoosePacks)>
                        <option value="">{{ __('messages.account_packs_default') }}</option>
                        @foreach ($options as $pack)
                            <option value="{{ $pack->id }}" @selected((int) optional($figurePrefs->get($type))->card_pack_id === (int) $pack->id)>
                                {{ $pack->name() }}@if ($pack->is_default) · {{ __('messages.account_packs_default') }}@endif
                            </option>
                        @endforeach
                    </select>
                </label>
            @endforeach

            <p class="credits-lead" style="margin-top:8px">{{ __('messages.account_packs_skins') }}</p>
            <label class="credits-note">
                <span>{{ __('messages.account_packs_back') }}<x-feature-marks feature="packs.backs" /></span>
                <span class="account-back-row">
                    <select name="card_back" data-back-select @disabled(! $canChooseBacks)>
                        <option value="" data-preview="">{{ __('messages.account_packs_default') }}</option>
                        @foreach ($backOptions as $back)
                            <option
                                value="{{ $back->id }}"
                                data-preview="{{ $back->fileUrl() }}"
                                @selected((int) optional($selectedBack)->id === (int) $back->id)
                            >
                                {{ $back->name() }}
                            </option>
                        @endforeach
                    </select>
                    <img
                        class="account-back-preview"
                        data-back-preview
                        alt="{{ __('messages.account_packs_back_preview') }}"
                        @if ($selectedBack?->fileUrl()) src="{{ $selectedBack->fileUrl() }}" @else hidden @endif
                    >
                </span>
            </label>

            @if ($canChoosePacks || $canChooseBacks)
                <button type="submit" class="credits-danger-btn" style="justify-self:start">{{ __('messages.account_packs_save') }}</button>
            @endif
        </form>

        <h2 class="credits-section-title">{{ __('messages.account_conversations') }}</h2>
        <p class="credits-lead">{{ __('messages.account_conversations_lead') }}</p>

        @if ($readings->isNotEmpty())
            <form
                method="POST"
                action="{{ route('account.readings.destroy-all') }}"
                class="account-toolbar"
                data-confirm="{{ __('messages.account_delete_all_confirm') }}"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="credits-danger-btn">{{ __('messages.account_delete_all') }}</button>
            </form>
        @endif

        @forelse ($readings as $log)
            <article class="credits-package account-reading">
                <div>
                    <h2>{{ \Illuminate\Support\Str::limit($log->question, 120) }}</h2>
                    <p>{{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                </div>
                <div class="account-actions">
                    <a class="credits-back" href="{{ route('account.readings.show', $log) }}">{{ __('messages.history_open') }}</a>
                    <form
                        method="POST"
                        action="{{ route('account.readings.destroy', $log) }}"
                        data-confirm="{{ __('messages.account_delete_confirm') }}"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="credits-danger-btn">{{ __('messages.account_delete') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="credits-lead">{{ __('messages.history_empty') }}</p>
        @endforelse

        <div style="margin-top:18px">{{ $readings->links() }}</div>

        @if ($paidFeaturesEnabled)
        <h2 class="credits-section-title">{{ __('messages.account_retained') }}</h2>
        <p class="credits-lead">{{ __('messages.account_retained_lead') }}</p>

        @forelse ($creditRequests as $item)
            <article class="credits-package">
                <div>
                    <h2>{{ $item->package_key }} · {{ __('messages.credits_balance', ['count' => (int) $item->credits]) }}</h2>
                    <p>{{ $item->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }} · {{ $item->status }}</p>
                </div>
            </article>
        @empty
            <p class="credits-lead">{{ __('messages.account_retained_empty') }}</p>
        @endforelse
        @endif

    </section>
    @include('templates.art-deco.partials.site-footer')
</main>
<script>
    (function () {
        var select = document.querySelector('[data-back-select]');
        var preview = document.querySelector('[data-back-preview]');
        if (!select || !preview) return;
        var show = function () {
            var option = select.options[select.selectedIndex];
            var src = option ? (option.getAttribute('data-preview') || '') : '';
            if (!src) {
                preview.hidden = true;
                preview.removeAttribute('src');
                return;
            }
            preview.hidden = false;
            preview.src = src;
        };
        select.addEventListener('change', show);
    })();
</script>
@include('partials.confirm-submit')
</body>
</html>

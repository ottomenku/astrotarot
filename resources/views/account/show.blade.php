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
                </dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_plan') }}</dt>
                <dd>{{ strtoupper($user->planKey()) }}</dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_credits') }}</dt>
                <dd>{{ __('messages.credits_balance', ['count' => (int) $user->credits]) }}</dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_created') }}</dt>
                <dd>{{ $user->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('messages.account_none') }}</dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_terms') }}</dt>
                <dd>{{ $user->terms_accepted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('messages.account_none') }}</dd>
            </div>
            <div>
                <dt>{{ __('messages.account_field_privacy') }}</dt>
                <dd>{{ $user->privacy_accepted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('messages.account_none') }}</dd>
            </div>
        </dl>

        <h2 class="credits-section-title">{{ __('messages.account_packs') }}<x-feature-marks feature="packs.choose" /></h2>
        <p class="credits-lead">{{ __('messages.account_packs_lead') }}</p>
        @unless ($canChoosePacks || $canChooseBacks || $canChooseBackgrounds)
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
            @foreach ($figureOptions as $options)
                @foreach ($options as $pack)
                    <fieldset class="account-pack-skins">
                        <legend>{{ $pack->name() }}</legend>
                        <label class="credits-note">
                            <span>{{ __('messages.account_packs_back') }}<x-feature-marks feature="packs.backs" /></span>
                            <select name="backs[{{ $pack->id }}]" @disabled(! $canChooseBacks)>
                                <option value="">{{ __('messages.account_packs_default') }}</option>
                                @foreach ($backOptions as $back)
                                    <option value="{{ $back->id }}" @selected((int) optional($backPrefs->get($pack->id))->card_pack_id === (int) $back->id)>
                                        {{ $back->name() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="credits-note">
                            <span>{{ __('messages.account_packs_light') }}<x-feature-marks feature="packs.backgrounds" /></span>
                            <select name="lights[{{ $pack->id }}]" @disabled(! $canChooseBackgrounds)>
                                <option value="">{{ __('messages.account_packs_default') }}</option>
                                @foreach ($lightOptions as $bg)
                                    <option value="{{ $bg->id }}" @selected((int) optional($lightPrefs->get($pack->id))->card_pack_id === (int) $bg->id)>
                                        {{ $bg->name() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="credits-note">
                            <span>{{ __('messages.account_packs_shadow') }}<x-feature-marks feature="packs.backgrounds" /></span>
                            <select name="shadows[{{ $pack->id }}]" @disabled(! $canChooseBackgrounds)>
                                <option value="">{{ __('messages.account_packs_default') }}</option>
                                @foreach ($shadowOptions as $bg)
                                    <option value="{{ $bg->id }}" @selected((int) optional($shadowPrefs->get($pack->id))->card_pack_id === (int) $bg->id)>
                                        {{ $bg->name() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </fieldset>
                @endforeach
            @endforeach

            @if ($canChoosePacks || $canChooseBacks || $canChooseBackgrounds)
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
                onsubmit="return confirm(@json(__('messages.account_delete_all_confirm')))"
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
                        onsubmit="return confirm(@json(__('messages.account_delete_confirm')))"
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

        <h2 class="credits-section-title">{{ __('messages.account_erasure_title') }}</h2>
        <p class="credits-lead">{{ __('messages.account_erasure_lead') }}</p>

        @if ($pendingDeletion)
            <div class="credits-flash">{{ __('messages.account_erasure_pending') }}</div>
        @else
            <form
                class="credits-package account-erasure"
                method="POST"
                action="{{ route('account.deletion-request.store') }}"
                onsubmit="return confirm(@json(__('messages.account_erasure_confirm')))"
            >
                @csrf
                <label class="credits-note">
                    <span>{{ __('messages.account_erasure_note') }}</span>
                    <textarea name="note" rows="2" maxlength="500"></textarea>
                </label>
                <button type="submit" class="credits-danger-btn">{{ __('messages.account_erasure_submit') }}</button>
            </form>
        @endif
    </section>
    @include('templates.art-deco.partials.site-footer')
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.history_detail') }} · AstroTarot</title>
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
                <a class="credits-back" href="{{ route('account.show') }}">{{ __('messages.account_menu') }}<x-feature-marks feature="account.show" /></a>
            </div>
        </div>
    </header>

    <section class="credits-panel">
        <p class="credits-kicker">{{ $log->created_at?->format('Y-m-d H:i') }}</p>
        <h1>{{ __('messages.history_detail') }}</h1>

        <h2 class="credits-section-title">{{ __('messages.history_question') }}</h2>
        <p class="credits-lead" style="font-style:italic">{{ $log->question }}</p>

        <h2 class="credits-section-title">{{ __('messages.history_answer') }}</h2>
        <p class="credits-lead" style="white-space:pre-wrap">{{ $log->response ?: __('messages.account_none') }}</p>

        @if (is_array($log->cards) && $log->cards !== [])
            <h2 class="credits-section-title">{{ __('messages.history_cards') }}</h2>
            @foreach ($log->cards as $card)
                <p class="credits-lead">
                    <strong>{{ $card['slot_title'] ?? '' }}:</strong>
                    {{ $card['figure_name'] ?? $card['figure_id'] ?? '' }}
                    @if (!empty($card['astro_caption']))
                        — {{ $card['astro_caption'] }}
                    @endif
                </p>
            @endforeach
        @endif

        <form
            method="POST"
            action="{{ route('account.readings.destroy', $log) }}"
            class="account-toolbar"
            onsubmit="return confirm(@json(__('messages.account_delete_confirm')))"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="credits-danger-btn">{{ __('messages.account_delete') }}</button>
        </form>
    </section>
    @include('templates.art-deco.partials.site-footer')
</main>
</body>
</html>

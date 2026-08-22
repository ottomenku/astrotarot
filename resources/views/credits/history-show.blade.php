<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.history_title') }} · AstroTarot</title>
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
                <a class="credits-back" href="{{ route('readings.history') }}">{{ __('messages.history_title') }}</a>
            </div>
        </div>
    </header>

    <section class="credits-panel">
        <p class="credits-kicker">{{ $log->created_at?->format('Y-m-d H:i') }}</p>
        <h1>{{ __('messages.history_detail') }}</h1>
        <h2 class="credits-section-title">{{ __('messages.history_question') }}</h2>
        <p class="credits-lead" style="font-style:italic">{{ $log->question }}</p>

        <h2 class="credits-section-title">{{ __('messages.history_answer') }}</h2>
        @if ($log->sourceLabel())
            <p class="credits-kicker">{{ __('messages.history_source') }}: {{ $log->sourceLabel() }}</p>
        @endif
        <p class="credits-lead" style="white-space:pre-wrap">{{ $log->response }}</p>

        @if (is_array($log->cards))
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
    </section>
</main>
</body>
</html>

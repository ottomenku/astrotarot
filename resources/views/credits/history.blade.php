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
                <a class="credits-back" href="{{ route('account.show') }}">{{ __('messages.account_menu') }}<x-feature-marks feature="account.show" /></a>
                <a class="credits-back" href="{{ route('credits.packages') }}">{{ __('messages.credits_menu') }}<x-feature-marks feature="credits.packages" /></a>
                <a class="credits-back" href="{{ route('home') }}">{{ __('messages.credits_back_home') }}</a>
            </div>
        </div>
    </header>

    <section class="credits-panel">
        <p class="credits-kicker">{{ __('messages.history_menu') }}<x-feature-marks feature="readings.history" /></p>
        <h1>{{ __('messages.history_title') }}</h1>
        <p class="credits-lead">{{ __('messages.history_lead') }}</p>

        @forelse ($logs as $log)
            <article class="credits-package" style="margin-bottom:12px">
                <div>
                    <h2 style="font-size:16px">{{ \Illuminate\Support\Str::limit($log->question, 120) }}</h2>
                    <p>{{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                </div>
                <a class="credits-back" href="{{ route('readings.history.show', $log) }}">{{ __('messages.history_open') }}</a>
            </article>
        @empty
            <p class="credits-lead">{{ __('messages.history_empty') }}</p>
        @endforelse

        <div style="margin-top:18px">{{ $logs->links() }}</div>
    </section>
    @include('templates.art-deco.partials.site-footer')
</main>
</body>
</html>

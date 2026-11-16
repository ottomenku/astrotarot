<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.astro_title') }} · AstroTarot</title>
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
        <p class="credits-kicker">{{ __('messages.astro_menu') }}</p>
        <h1>{{ __('messages.astro_title') }}</h1>
        <p class="credits-lead">{{ __('messages.astro_lead') }}</p>

        @php
            $gender = match ($user->gender) {
                'female' => __('messages.intro_gender_female'),
                'male' => __('messages.intro_gender_male'),
                'other' => __('messages.intro_gender_other'),
                default => null,
            };
            $hasSelf = $user->birth_at !== null
                || filled($user->birth_country)
                || filled($user->birth_city)
                || filled($user->introduction)
                || $gender !== null;
        @endphp

        <h2 class="credits-section-title">{{ __('messages.astro_self') }}</h2>
        @if ($hasSelf)
            <dl class="account-dl">
                <div>
                    <dt>{{ __('messages.astro_name') }}</dt>
                    <dd>{{ $user->name }}</dd>
                </div>
                @if ($user->birth_at)
                    <div>
                        <dt>{{ __('messages.intro_birth') }}</dt>
                        <dd>{{ $user->birth_at->timezone('Europe/Budapest')->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
                @if (filled($user->birth_country))
                    <div>
                        <dt>{{ __('messages.intro_birth_country') }}</dt>
                        <dd>{{ $user->birth_country }}</dd>
                    </div>
                @endif
                @if (filled($user->birth_city))
                    <div>
                        <dt>{{ __('messages.intro_birth_city') }}</dt>
                        <dd>{{ $user->birth_city }}</dd>
                    </div>
                @endif
                @if ($user->birth_latitude !== null && $user->birth_longitude !== null)
                    <div>
                        <dt>{{ __('messages.intro_latitude') }} · {{ __('messages.intro_longitude') }}</dt>
                        <dd>{{ $user->birth_latitude }} · {{ $user->birth_longitude }}</dd>
                    </div>
                @endif
                @if ($gender)
                    <div>
                        <dt>{{ __('messages.intro_gender') }}</dt>
                        <dd>{{ $gender }}</dd>
                    </div>
                @endif
                @if (filled($user->introduction))
                    <div>
                        <dt>{{ __('messages.intro_text') }}</dt>
                        <dd>{{ $user->introduction }}</dd>
                    </div>
                @endif
            </dl>
        @else
            <p class="credits-lead">{{ __('messages.astro_self_empty') }}</p>
        @endif

        <h2 class="credits-section-title">{{ __('messages.astro_relations') }}</h2>
        @forelse ($profiles as $profile)
            <article class="credits-package">
                <div>
                    <h2>{{ $profile->label }}</h2>
                    <p>
                        @if ($profile->birth_at)
                            {{ __('messages.intro_birth') }}: {{ $profile->birth_at->timezone('Europe/Budapest')->format('Y-m-d H:i') }}
                        @endif
                        @if (filled($profile->birth_country) || filled($profile->birth_city))
                            · {{ trim($profile->birth_country.' '.$profile->birth_city) }}
                        @endif
                    </p>
                    @if ($profile->birth_latitude !== null && $profile->birth_longitude !== null)
                        <p>{{ __('messages.intro_latitude') }}: {{ $profile->birth_latitude }} · {{ __('messages.intro_longitude') }}: {{ $profile->birth_longitude }}</p>
                    @endif
                    @if (filled($profile->introduction))
                        <p>{{ $profile->introduction }}</p>
                    @endif
                </div>
            </article>
        @empty
            <p class="credits-lead">{{ __('messages.astro_relations_empty') }}</p>
        @endforelse
    </section>
    @include('templates.art-deco.partials.site-footer')
</main>
</body>
</html>

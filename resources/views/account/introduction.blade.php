<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.intro_title') }} · AstroTarot</title>
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
        <p class="credits-kicker">{{ __('messages.intro_menu') }}</p>
        <h1>{{ __('messages.intro_title') }}</h1>
        <p class="credits-lead">{{ __('messages.intro_lead') }}</p>

        @if (session('status'))
            <div class="credits-flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="credits-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.introduction.update') }}">
            @csrf
            @method('PUT')

            @php
                $birthValue = old('birth_at');
                if ($birthValue === null && $user->birth_at !== null) {
                    $birthValue = $user->birth_at->timezone('Europe/Budapest')->format('Y-m-d\TH:i');
                }
            @endphp
            <label class="credits-note">
                <span>{{ __('messages.intro_birth') }}</span>
                <input type="datetime-local" name="birth_at" value="{{ $birthValue }}">
            </label>

            <label class="credits-note">
                <span>{{ __('messages.intro_gender') }}</span>
                <select name="gender">
                    <option value="">{{ __('messages.intro_gender_unset') }}</option>
                    @foreach (['female' => 'intro_gender_female', 'male' => 'intro_gender_male', 'other' => 'intro_gender_other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ __('messages.'.$label) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="credits-note">
                <span>{{ __('messages.intro_text') }}</span>
                <textarea name="introduction" rows="4" maxlength="200" data-intro-input>{{ old('introduction', $user->introduction) }}</textarea>
                <span data-intro-count>{{ mb_strlen((string) old('introduction', $user->introduction)) }}/200</span>
            </label>
            <p class="credits-lead">{{ __('messages.intro_text_hint') }}</p>

            <button type="submit" class="credits-back">{{ __('messages.intro_save') }}</button>
        </form>
    </section>
</main>
<script>
    const input = document.querySelector('[data-intro-input]');
    const count = document.querySelector('[data-intro-count]');
    if (input && count) {
        const paint = () => { count.textContent = `${input.value.length}/200`; };
        input.addEventListener('input', paint);
        paint();
    }
</script>
</body>
</html>

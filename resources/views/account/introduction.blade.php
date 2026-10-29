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

        <form method="POST" action="{{ route('account.introduction.update') }}" data-cities-url="{{ route('account.introduction.cities') }}">
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

            @include('account.partials.place-combo', [
                'kind' => 'country',
                'pair' => 'birth',
                'name' => 'birth_country',
                'label' => __('messages.intro_birth_country'),
                'placeholder' => __('messages.intro_country_search'),
                'value' => $birthCountry,
                'code' => $birthCountryCode,
                'listId' => 'birth-country-list',
            ])
            @include('account.partials.place-combo', [
                'kind' => 'city',
                'pair' => 'birth',
                'name' => 'birth_city',
                'label' => __('messages.intro_birth_city'),
                'placeholder' => __('messages.intro_city_search'),
                'value' => old('birth_city', $user->birth_city),
                'code' => null,
                'listId' => 'birth-city-list',
            ])
            @if ($user->birth_latitude !== null && $user->birth_longitude !== null)
                <p class="place-coords">{{ __('messages.intro_latitude') }}: {{ $user->birth_latitude }} · {{ __('messages.intro_longitude') }}: {{ $user->birth_longitude }}</p>
            @endif

            <label class="credits-note">
                <span>{{ __('messages.intro_gender') }}</span>
                <select name="gender">
                    <option value="">{{ __('messages.intro_gender_unset') }}</option>
                    @foreach (['female' => 'intro_gender_female', 'male' => 'intro_gender_male', 'other' => 'intro_gender_other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ __('messages.'.$label) }}</option>
                    @endforeach
                </select>
            </label>

            @include('account.partials.place-combo', [
                'kind' => 'country',
                'pair' => 'residence',
                'name' => 'residence_country',
                'label' => __('messages.intro_residence_country'),
                'placeholder' => __('messages.intro_country_search'),
                'value' => $residenceCountry,
                'code' => $residenceCountryCode,
                'listId' => 'residence-country-list',
            ])
            @include('account.partials.place-combo', [
                'kind' => 'city',
                'pair' => 'residence',
                'name' => 'residence_city',
                'label' => __('messages.intro_residence_city'),
                'placeholder' => __('messages.intro_city_search'),
                'value' => old('residence_city', $user->residence_city),
                'code' => null,
                'listId' => 'residence-city-list',
            ])
            @if ($user->residence_latitude !== null && $user->residence_longitude !== null)
                <p class="place-coords">{{ __('messages.intro_latitude') }}: {{ $user->residence_latitude }} · {{ __('messages.intro_longitude') }}: {{ $user->residence_longitude }}</p>
            @endif

            <label class="credits-note">
                <span>{{ __('messages.intro_text') }}</span>
                <textarea name="introduction" rows="4" maxlength="500" data-intro-input>{{ old('introduction', $user->introduction) }}</textarea>
                <span data-intro-count>{{ mb_strlen((string) old('introduction', $user->introduction)) }}/500</span>
            </label>
            <p class="credits-lead">{{ __('messages.intro_text_hint') }}</p>

            <button type="submit" class="credits-back">{{ __('messages.intro_save') }}</button>
        </form>
    </section>
</main>
<script type="application/json" id="place-countries">{!! json_encode($countries, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script>
    const introInput = document.querySelector('[data-intro-input]');
    const introCount = document.querySelector('[data-intro-count]');
    if (introInput && introCount) {
        const paint = () => { introCount.textContent = `${introInput.value.length}/500`; };
        introInput.addEventListener('input', paint);
        paint();
    }

    const countries = JSON.parse(document.getElementById('place-countries')?.textContent || '[]');
    const form = document.querySelector('[data-cities-url]');
    const needCountry = @json(__('messages.intro_city_need_country'));
    const noMatches = @json(__('messages.intro_place_empty'));
    const cityTimers = new WeakMap();
    const cityRequests = new WeakMap();

    const comboInput = (combo) => combo.querySelector('.place-combo-input');
    const comboHidden = (combo) => combo.querySelector('[data-place-value]');
    const comboList = (combo) => combo.querySelector('.place-combo-list');
    const pairCombo = (pair, kind) => document.querySelector(`[data-place-combo][data-pair="${pair}"][data-kind="${kind}"]`);

    function closeList(combo) {
        const list = comboList(combo);
        const input = comboInput(combo);
        if (list) list.hidden = true;
        input?.setAttribute('aria-expanded', 'false');
    }

    function openList(combo, items) {
        const list = comboList(combo);
        const input = comboInput(combo);
        list.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('li');
            empty.className = 'place-combo-empty';
            empty.textContent = noMatches;
            list.appendChild(empty);
        } else {
            items.forEach((item, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'place-combo-option' + (index === 0 ? ' is-active' : '');
                option.setAttribute('role', 'option');
                option.dataset.value = item.name;
                if (item.code) option.dataset.code = item.code;
                option.textContent = item.name;
                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    choose(combo, item);
                });
                list.appendChild(option);
            });
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function showMessage(combo, message) {
        const list = comboList(combo);
        list.innerHTML = '';
        const empty = document.createElement('li');
        empty.className = 'place-combo-empty';
        empty.textContent = message;
        list.appendChild(empty);
        list.hidden = false;
        comboInput(combo).setAttribute('aria-expanded', 'true');
    }

    function clearCity(pair) {
        const city = pairCombo(pair, 'city');
        if (!city) return;
        comboInput(city).value = '';
        comboHidden(city).value = '';
        city.dataset.selected = '';
        closeList(city);
    }

    function choose(combo, item) {
        const input = comboInput(combo);
        input.value = item.name;
        comboHidden(combo).value = item.name;
        combo.dataset.selected = item.name;
        if (combo.dataset.kind === 'country') {
            const previous = combo.dataset.code || '';
            combo.dataset.code = item.code || '';
            if (previous !== combo.dataset.code) clearCity(combo.dataset.pair);
        }
        closeList(combo);
    }

    function countryMatches(query) {
        const needle = query.trim().toLocaleLowerCase();
        const matches = needle === ''
            ? countries
            : countries.filter((country) => country.name.toLocaleLowerCase().includes(needle));
        return matches.slice(0, 12);
    }

    function refreshCountry(combo) {
        openList(combo, countryMatches(comboInput(combo).value));
    }

    async function searchCities(combo) {
        const query = comboInput(combo).value.trim();
        const country = pairCombo(combo.dataset.pair, 'country');
        const code = country?.dataset.code || '';
        if (!code) {
            showMessage(combo, needCountry);
            return;
        }
        if (query.length < 2) {
            closeList(combo);
            return;
        }
        cityRequests.get(combo)?.abort();
        const controller = new AbortController();
        cityRequests.set(combo, controller);
        try {
            const url = new URL(form.dataset.citiesUrl, window.location.origin);
            url.searchParams.set('country', code);
            url.searchParams.set('q', query);
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) {
                showMessage(combo, noMatches);
                return;
            }
            const payload = await response.json();
            const cities = Array.isArray(payload.cities) ? payload.cities : [];
            openList(combo, cities.filter((name) => typeof name === 'string' && name !== '').map((name) => ({ name })));
        } catch (error) {
            if (error.name !== 'AbortError') showMessage(combo, noMatches);
        }
    }

    function scheduleCities(combo) {
        window.clearTimeout(cityTimers.get(combo));
        cityTimers.set(combo, window.setTimeout(() => searchCities(combo), 250));
    }

    function commit(combo) {
        const input = comboInput(combo);
        const typed = input.value.trim();
        if (typed === '') {
            comboHidden(combo).value = '';
            combo.dataset.selected = '';
            if (combo.dataset.kind === 'country') {
                combo.dataset.code = '';
                clearCity(combo.dataset.pair);
            }
            closeList(combo);
            return;
        }
        const selected = combo.dataset.selected || '';
        if (typed !== selected) {
            input.value = selected;
            comboHidden(combo).value = selected;
        }
        closeList(combo);
    }

    document.querySelectorAll('[data-place-combo]').forEach((combo) => {
        const input = comboInput(combo);
        input.addEventListener('focus', () => {
            if (combo.dataset.kind === 'country') {
                refreshCountry(combo);
                return;
            }
            if (!(pairCombo(combo.dataset.pair, 'country')?.dataset.code)) showMessage(combo, needCountry);
            else scheduleCities(combo);
        });
        input.addEventListener('input', () => {
            if (combo.dataset.kind === 'country') {
                const typed = input.value.trim().toLocaleLowerCase();
                const exact = countries.filter((country) => country.name.toLocaleLowerCase() === typed);
                if (exact.length === 1) {
                    choose(combo, exact[0]);
                    return;
                }
                refreshCountry(combo);
                return;
            }
            scheduleCities(combo);
        });
        input.addEventListener('blur', () => {
            window.setTimeout(() => commit(combo), 120);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (comboList(combo).hidden) {
                    if (combo.dataset.kind === 'country') refreshCountry(combo);
                    else searchCities(combo);
                }
                const visible = [...combo.querySelectorAll('.place-combo-option')];
                if (!visible.length) return;
                const active = combo.querySelector('.place-combo-option.is-active');
                const activeIndex = visible.indexOf(active);
                const next = event.key === 'ArrowDown'
                    ? visible[Math.min(visible.length - 1, Math.max(0, activeIndex) + (activeIndex === -1 ? 0 : 1))]
                    : visible[Math.max(0, activeIndex <= 0 ? 0 : activeIndex - 1)];
                visible.forEach((option) => option.classList.remove('is-active'));
                next.classList.add('is-active');
                next.scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter') {
                const current = combo.querySelector('.place-combo-option.is-active');
                if (current && !comboList(combo).hidden) {
                    event.preventDefault();
                    choose(combo, { name: current.dataset.value, code: current.dataset.code || '' });
                }
            } else if (event.key === 'Escape') {
                closeList(combo);
            }
        });
    });

    form?.addEventListener('submit', () => {
        document.querySelectorAll('[data-place-combo]').forEach((combo) => commit(combo));
    });

    document.addEventListener('pointerdown', (event) => {
        document.querySelectorAll('[data-place-combo]').forEach((combo) => {
            if (!combo.contains(event.target)) closeList(combo);
        });
    });
</script>
</body>
</html>

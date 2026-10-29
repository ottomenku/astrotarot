@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.ephemeris.title') }}</h1>

        <form method="GET" action="{{ route('admin.ephemeris.index') }}" style="margin-bottom:1.5rem">
            <div class="flash" style="margin-bottom:1rem">
                <strong>{{ __('admin.ephemeris.card_chart') }}:</strong>
                @if ($chartMode === 'now')
                    {{ __('admin.ephemeris.card_chart_now') }}
                @else
                    {{ __('admin.ephemeris.card_chart_fixed') }} — {{ $chartDatetimeUtc }}
                @endif
            </div>
            <div class="row">
                <div class="field">
                    <label for="datetime_utc">{{ __('admin.ephemeris.datetime') }}</label>
                    <input
                        id="datetime_utc"
                        type="datetime-local"
                        name="datetime_utc"
                        value="{{ old('datetime_utc', $input['datetime_local']) }}"
                        step="60"
                        required
                    >
                    <small style="display:block;margin-top:.35rem;color:#5c534c">{{ __('admin.ephemeris.datetime_hint') }}</small>
                </div>
                <div class="field">
                    <label for="lat">{{ __('admin.ephemeris.lat') }}</label>
                    <input id="lat" type="number" step="any" name="lat" value="{{ old('lat', $input['lat']) }}" required>
                </div>
                <div class="field">
                    <label for="lon">{{ __('admin.ephemeris.lon') }}</label>
                    <input id="lon" type="number" step="any" name="lon" value="{{ old('lon', $input['lon']) }}" required>
                </div>
                <div class="field">
                    <p style="margin:.4rem 0 0">{{ __('admin.ephemeris.coordinates_place', ['place' => $locationLabel]) }}</p>
                </div>
                <div class="field">
                    <label for="sidereal">{{ __('admin.ephemeris.sidereal') }}</label>
                    <select id="sidereal" name="sidereal">
                        <option value="0" @selected(! $input['sidereal'])>Tropical</option>
                        <option value="1" @selected($input['sidereal'])>Sidereal</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
                <button type="submit">{{ __('admin.ephemeris.calculate') }}</button>
                <a class="btn" href="{{ route('admin.ephemeris.index', ['moment_mode' => 'now']) }}">{{ __('admin.ephemeris.use_now') }}</a>
            </div>
        </form>

        @if ($error)
            <div class="error">{{ __('admin.ephemeris.error') }}: {{ $error }}</div>
        @endif

        @if ($result)
            @php
                $scoring = is_array($result['scoring'] ?? null) ? $result['scoring'] : [];
                $signScores = $scoring['jegyek'] ?? null;
                $aspectRows = is_array($scoring['fenyszogek'] ?? null) ? $scoring['fenyszogek'] : [];
                $polaritySummary = is_array($scoring['polaritas_osszesites'] ?? null) ? $scoring['polaritas_osszesites'] : null;
            @endphp

            @if (is_array($polaritySummary))
                <section style="margin-bottom:1.25rem">
                    <h2 style="font-size:1.1rem;margin-bottom:.75rem">{{ __('admin.ephemeris.summary') }}</h2>
                    <div class="row">
                        @foreach (['bolygok' => 'summary_planets', 'jegyek' => 'summary_signs'] as $key => $label)
                            @php
                                $row = is_array($polaritySummary[$key] ?? null) ? $polaritySummary[$key] : ['pont' => 0, 'besorolas' => 'positive'];
                                $points = (int) ($row['pont'] ?? 0);
                                $class = ($row['besorolas'] ?? 'positive') === 'negative' ? 'negative' : 'positive';
                            @endphp
                            <div class="field">
                                <strong>{{ __('admin.ephemeris.'.$label) }}</strong><br>
                                {{ $points > 0 ? '+' : '' }}{{ $points }}
                                · {{ __('admin.ephemeris.polarity_'.$class) }}
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <table>
                <thead>
                    <tr>
                        <th>{{ __('admin.ephemeris.object') }}</th>
                        <th>{{ __('admin.ephemeris.sign') }}</th>
                        <th>{{ __('admin.ephemeris.degree') }}</th>
                        <th>{{ __('admin.ephemeris.house') }}</th>
                        <th>{{ __('admin.ephemeris.fixed_star') }}</th>
                        <th>{{ __('admin.ephemeris.tone') }}</th>
                        <th>{{ __('admin.ephemeris.explanation') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['objects'] as $object)
                        <tr>
                            <td>{{ $object['name'] }}</td>
                            <td>{{ $object['sign'] }}</td>
                            <td>{{ number_format((float) $object['degree'], 4) }}°</td>
                            <td>{{ isset($object['house']) ? (int) $object['house'] : '—' }}</td>
                            <td>{{ $object['fixed_star'] ?? '—' }}</td>
                            <td>
                                @if (isset($object['polaritas_pont']))
                                    {{ (int) $object['polaritas_pont'] > 0 ? '+' : '' }}{{ (int) $object['polaritas_pont'] }}
                                    · {{ __('admin.ephemeris.polarity_'.(($object['polaritas_besorolas'] ?? 'positive') === 'negative' ? 'negative' : 'positive')) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if (! empty($object['polaritas_magyarazat']))
                                    <textarea readonly rows="3" style="width:100%;min-width:16rem;box-sizing:border-box">{{ $object['polaritas_magyarazat'] }}</textarea>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h2 style="font-size:1.1rem;margin:1.25rem 0 .75rem">{{ __('admin.ephemeris.aspects') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('admin.ephemeris.object') }}</th>
                        <th>{{ __('admin.ephemeris.aspects') }}</th>
                        <th>{{ __('admin.ephemeris.object') }}</th>
                        <th>{{ __('admin.ephemeris.tone') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($aspectRows as $aspect)
                        @php
                            $aspectPoints = (int) ($aspect['points'] ?? 0);
                            $aspectClass = $aspectPoints < 0 ? 'negative' : 'positive';
                        @endphp
                        <tr>
                            <td>{{ $aspect['left'] ?? '—' }}</td>
                            <td>{{ __('admin.ephemeris.aspect_'.($aspect['kind'] ?? 'conjunction')) }}</td>
                            <td>{{ $aspect['right'] ?? '—' }}</td>
                            <td>
                                {{ $aspectPoints > 0 ? '+' : '' }}{{ $aspectPoints }}
                                · {{ __('admin.ephemeris.polarity_'.$aspectClass) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">{{ __('admin.ephemeris.polarity_no_aspects') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (is_array($signScores) && $signScores !== [])
                <h2 style="font-size:1.1rem;margin:1.25rem 0 .75rem">{{ __('admin.ephemeris.signs') }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('admin.ephemeris.sign') }}</th>
                            <th>{{ __('admin.ephemeris.tone') }}</th>
                            <th>{{ __('admin.ephemeris.explanation') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($signScores as $sign)
                            <tr>
                                <td>{{ $sign['nev'] ?? '—' }}</td>
                                <td>
                                    {{ (int) ($sign['pont'] ?? 0) > 0 ? '+' : '' }}{{ (int) ($sign['pont'] ?? 0) }}
                                    · {{ __('admin.ephemeris.polarity_'.(($sign['besorolas'] ?? 'positive') === 'negative' ? 'negative' : 'positive')) }}
                                </td>
                                <td>
                                    <textarea readonly rows="3" style="width:100%;min-width:16rem;box-sizing:border-box">{{ $sign['magyarazat'] ?? '' }}</textarea>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </main>
@endsection

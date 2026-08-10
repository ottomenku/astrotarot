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
            @if (! empty($result['scoring']['summary']))
                @php
                    $summary = $result['scoring']['summary'];
                    $summaryClean = $result['scoring']['summary_clean'] ?? null;
                    $tarotPositions = $result['scoring']['tarot_helyek']['positions'] ?? null;
                @endphp
                <section style="margin-bottom:1.25rem">
                    <h2 style="font-size:1.1rem;margin-bottom:.75rem">{{ __('admin.ephemeris.summary') }}</h2>
                    <div class="row">
                        <div class="field">
                            <strong>{{ __('admin.ephemeris.element_dominance') }}</strong><br>
                            {{ $summary['elem_dominancia']['nev'] ?? '—' }}
                            ({{ $summary['elem_dominancia']['ertek'] ?? '—' }})
                        </div>
                        <div class="field">
                            <strong>{{ __('admin.ephemeris.modality_dominance') }}</strong><br>
                            {{ $summary['minoseg_dominancia']['nev'] ?? '—' }}
                            ({{ $summary['minoseg_dominancia']['ertek'] ?? '—' }})
                        </div>
                        <div class="field">
                            <strong>{{ __('admin.ephemeris.tone_summary') }}</strong><br>
                            {{ $summary['pozitiv_negativ']['nev'] ?? '—' }}
                            ({{ __('admin.ephemeris.tone_net') }}: {{ $summary['pozitiv_negativ']['netto_ertek'] ?? '—' }})
                        </div>
                    </div>
                </section>

                @if ($summaryClean)
                    <section style="margin-bottom:1.25rem">
                        <h2 style="font-size:1.1rem;margin-bottom:.75rem">{{ __('admin.ephemeris.summary_clean') }}</h2>
                        <div class="row">
                            <div class="field">
                                <strong>{{ __('admin.ephemeris.element_dominance') }}</strong><br>
                                {{ $summaryClean['elem_dominancia']['nev'] ?? '—' }}
                                ({{ $summaryClean['elem_dominancia']['ertek'] ?? '—' }})
                            </div>
                            <div class="field">
                                <strong>{{ __('admin.ephemeris.modality_dominance') }}</strong><br>
                                {{ $summaryClean['minoseg_dominancia']['nev'] ?? '—' }}
                                ({{ $summaryClean['minoseg_dominancia']['ertek'] ?? '—' }})
                            </div>
                            <div class="field">
                                <strong>{{ __('admin.ephemeris.tone_summary') }}</strong><br>
                                {{ $summaryClean['pozitiv_negativ']['nev'] ?? '—' }}
                                ({{ __('admin.ephemeris.tone_net') }}: {{ $summaryClean['pozitiv_negativ']['netto_ertek'] ?? '—' }})
                            </div>
                        </div>
                    </section>
                @endif

                @if (is_array($tarotPositions) && $tarotPositions !== [])
                    <section style="margin-bottom:1.25rem">
                        <h2 style="font-size:1.1rem;margin-bottom:.75rem">{{ __('admin.ephemeris.tarot_slots') }}</h2>
                        <div class="row">
                            @foreach ($tarotPositions as $position)
                                <div class="field" title="{{ $position['magyarazat'] ?? '' }}">
                                    <strong>{{ $position['hely'] ?? '—' }}. {{ $position['nev'] ?? '' }}</strong><br>
                                    {{ $position['besorolas'] ?? '—' }}
                                    ({{ $position['ertek'] ?? '—' }})
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif

            <table>
                <thead>
                    <tr>
                        <th>{{ __('admin.ephemeris.object') }}</th>
                        <th>{{ __('admin.ephemeris.sign') }}</th>
                        <th>{{ __('admin.ephemeris.degree') }}</th>
                        <th>{{ __('admin.ephemeris.fixed_star') }}</th>
                        <th>{{ __('admin.ephemeris.element') }}</th>
                        <th>{{ __('admin.ephemeris.modality') }}</th>
                        <th>{{ __('admin.ephemeris.sign_polarity') }}</th>
                        <th>{{ __('admin.ephemeris.tone') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['objects'] as $object)
                        <tr title="{{ $object['magyarazat'] ?? '' }}">
                            <td>{{ $object['name'] }}</td>
                            <td>{{ $object['sign'] }}</td>
                            <td>{{ number_format((float) $object['degree'], 4) }}°</td>
                            <td>{{ $object['fixed_star'] ?? '—' }}</td>
                            <td>
                                @if (isset($object['elem']))
                                    {{ $object['elem'] }} ({{ number_format((float) $object['elem_ertek'], 2) }})
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if (isset($object['minoseg']))
                                    {{ $object['minoseg'] }} ({{ number_format((float) $object['minoseg_ertek'], 2) }})
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $object['jegy_polaritas'] ?? '—' }}</td>
                            <td>
                                @if (isset($object['pozitiv_negativ_besorolas']))
                                    {{ $object['pozitiv_negativ_besorolas'] }}
                                    ({{ number_format((float) $object['pozitiv_negativ_ertek'], 2) }})
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </main>
@endsection

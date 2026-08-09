@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.ephemeris.title') }}</h1>

        <form method="GET" action="{{ route('admin.ephemeris.index') }}" style="margin-bottom:1.5rem">
            <div class="row">
                <div class="field">
                    <label for="datetime_utc">{{ __('admin.ephemeris.datetime') }}</label>
                    <input id="datetime_utc" type="text" name="datetime_utc" value="{{ old('datetime_utc', $input['datetime_utc']) }}" placeholder="2026-08-09T12:00:00Z" required>
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
            <button type="submit">{{ __('admin.ephemeris.calculate') }}</button>
        </form>

        @if ($error)
            <div class="error">{{ __('admin.ephemeris.error') }}: {{ $error }}</div>
        @endif

        @if ($result)
            <table>
                <thead>
                    <tr>
                        <th>{{ __('admin.ephemeris.object') }}</th>
                        <th>{{ __('admin.ephemeris.sign') }}</th>
                        <th>{{ __('admin.ephemeris.degree') }}</th>
                        <th>{{ __('admin.ephemeris.fixed_star') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['objects'] as $object)
                        <tr>
                            <td>{{ $object['name'] }}</td>
                            <td>{{ $object['sign'] }}</td>
                            <td>{{ number_format((float) $object['degree'], 4) }}°</td>
                            <td>{{ $object['fixed_star'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </main>
@endsection

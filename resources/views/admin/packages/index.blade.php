@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="row" style="align-items:center;margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.packages.title') }}</h1>
            <a class="btn btn-primary" href="{{ route('admin.packages.create') }}">{{ __('admin.packages.create') }}</a>
        </div>
        <p class="muted">{{ __('admin.packages.hint') }} <a href="{{ route('admin.feature-access.edit') }}">{{ __('admin.nav.feature_access') }}</a></p>

        <table>
            <thead>
                <tr>
                    <th>{{ __('admin.packages.name') }}</th>
                    <th>{{ __('admin.packages.price_huf') }}</th>
                    <th>{{ __('admin.packages.price_usd') }}</th>
                    <th>{{ __('admin.packages.period') }}</th>
                    <th>{{ __('admin.packages.credits') }}</th>
                    <th>{{ __('admin.packages.active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($packages as $package)
                    <tr>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:.4rem">
                                @if ($package->iconSvg() !== '')
                                    {!! $package->iconSvg() !!}
                                @endif
                                {{ $package->name_hu }}
                            </span>
                            <div class="muted">{{ $package->slug }}</div>
                        </td>
                        <td>{{ number_format($package->price_huf, 0, ',', ' ') }} Ft</td>
                        <td>${{ rtrim(rtrim(number_format((float) $package->price_usd, 2, '.', ''), '0'), '.') }}</td>
                        <td>{{ __('admin.packages.period_'.$package->period) }}</td>
                        <td>{{ $package->credits }}</td>
                        <td>{{ $package->is_active ? '✓' : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.packages.edit', $package) }}">{{ __('admin.packages.edit') }}</a>
                            <form method="POST" action="{{ route('admin.packages.toggle', $package) }}" style="display:inline;margin-left:.5rem">
                                @csrf
                                <button type="submit">{{ $package->is_active ? __('admin.packages.deactivate') : __('admin.packages.activate') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">{{ __('admin.packages.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>
@endsection

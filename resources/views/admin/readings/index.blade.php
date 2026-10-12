@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.readings.title') }}</h1>

        <form method="GET" action="{{ route('admin.readings.index') }}" class="row" style="margin-bottom:1rem">
            <div class="field">
                <label for="q">{{ __('admin.readings.search') }}</label>
                <input id="q" name="q" value="{{ request('q') }}">
            </div>
            <div class="field" style="align-self:end">
                <button type="submit">{{ __('admin.readings.filter') }}</button>
            </div>
        </form>

        <table class="admin-compact">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.readings.created') }}</th>
                    <th>{{ __('admin.readings.user') }}</th>
                    <th>{{ __('admin.readings.question') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if ($log->user)
                                {{ $log->user->name }}
                                <div class="muted">{{ $log->user->email }}</div>
                            @else
                                <span class="muted">{{ __('admin.readings.guest') }}</span>
                            @endif
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($log->question, 80) }}</td>
                        <td class="admin-actions">
                            <a class="admin-icon-btn is-blue" href="{{ route('admin.readings.preview', $log) }}" title="{{ __('admin.readings.preview') }}">{!! \App\Support\AdminIcons::eye() !!}</a>
                            <a class="admin-icon-btn is-red" href="{{ route('admin.readings.show', $log) }}" title="{{ __('admin.readings.view') }}">{!! \App\Support\AdminIcons::eye() !!}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">{{ __('admin.readings.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem">
            {{ $logs->links() }}
        </div>
    </main>
@endsection

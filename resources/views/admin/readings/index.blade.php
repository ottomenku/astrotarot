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
            <div class="field">
                <label for="status">{{ __('admin.readings.status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('admin.readings.status_all') }}</option>
                    <option value="success" @selected(request('status') === 'success')>{{ __('admin.readings.status_success') }}</option>
                    <option value="error" @selected(request('status') === 'error')>{{ __('admin.readings.status_error') }}</option>
                    <option value="pending" @selected(request('status') === 'pending')>{{ __('admin.readings.status_pending') }}</option>
                </select>
            </div>
            <div class="field" style="align-self:end">
                <button type="submit">{{ __('admin.readings.filter') }}</button>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.readings.created') }}</th>
                    <th>{{ __('admin.readings.locale') }}</th>
                    <th>{{ __('admin.readings.status') }}</th>
                    <th>{{ __('admin.readings.question') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ strtoupper($log->locale) }}</td>
                        <td>{{ $log->status }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($log->question, 80) }}</td>
                        <td>
                            <a href="{{ route('admin.readings.show', $log) }}">{{ __('admin.readings.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">{{ __('admin.readings.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem">
            {{ $logs->links() }}
        </div>
    </main>
@endsection

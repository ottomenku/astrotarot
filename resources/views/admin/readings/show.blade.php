@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <p><a href="{{ route('admin.readings.index') }}">← {{ __('admin.readings.back') }}</a></p>
        <h1>{{ __('admin.readings.show_title') }} #{{ $log->id }}</h1>

        <div class="row">
            <div class="field">
                <label>{{ __('admin.readings.created') }}</label>
                <p>{{ $log->created_at?->format('Y-m-d H:i:s') }}</p>
            </div>
            <div class="field">
                <label>{{ __('admin.readings.user') }}</label>
                <p>
                    @if ($log->user)
                        {{ $log->user->name }} · {{ $log->user->email }}
                    @else
                        {{ __('admin.readings.guest') }}
                    @endif
                </p>
            </div>
            <div class="field">
                <label>{{ __('admin.readings.status') }}</label>
                <p>{{ $log->status }}</p>
            </div>
            <div class="field">
                <label>{{ __('admin.readings.locale') }}</label>
                <p>{{ strtoupper($log->locale) }}</p>
            </div>
            <div class="field">
                <label>{{ __('admin.readings.model') }}</label>
                <p>{{ $log->sourceLabel() ?: ($log->model ?: '—') }}</p>
            </div>
            <div class="field">
                <label>{{ __('admin.readings.cost') }}</label>
                <p>
                    {{ __('admin.readings.cost_input') }}: {{ number_format($cost['input_tokens'], 0, ',', ' ') }}<br>
                    {{ __('admin.readings.cost_output') }}: {{ number_format($cost['output_tokens'], 0, ',', ' ') }}<br>
                    {{ __('admin.readings.cost_total') }}: {{ number_format($cost['total_tokens'], 0, ',', ' ') }}<br>
                    @if ($cost['local'])
                        {{ __('admin.readings.cost_local') }}
                    @elseif ($cost['huf_label'] !== '')
                        {{ __('admin.readings.cost_huf') }}: {{ $cost['huf_label'] }}
                    @else
                        {{ __('admin.readings.cost_unknown') }}
                    @endif
                </p>
                <p class="muted">{{ __('admin.readings.cost_note', ['rate' => \App\Services\ReadingCostEstimate::USD_HUF]) }}</p>
            </div>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.question') }}</label>
            <p>{{ $log->question }}</p>
        </div>

        @if ($log->error)
            <div class="field">
                <label>{{ __('admin.readings.error') }}</label>
                <pre style="white-space:pre-wrap">{{ $log->error }}</pre>
            </div>
        @endif

        <div class="field">
            <label>{{ __('admin.readings.cards') }}</label>
            <pre style="white-space:pre-wrap">{{ json_encode($log->cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.astro') }}</label>
            <pre style="white-space:pre-wrap">{{ json_encode($log->astro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.system_prompt') }}</label>
            <pre style="white-space:pre-wrap">{{ $log->system_prompt }}</pre>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.user_prompt') }}</label>
            <pre style="white-space:pre-wrap">{{ $log->user_prompt }}</pre>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.response') }}</label>
            <pre style="white-space:pre-wrap">{{ $log->response ?: '—' }}</pre>
        </div>
    </main>
@endsection

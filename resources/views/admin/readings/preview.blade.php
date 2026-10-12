@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <p><a href="{{ route('admin.readings.index') }}">← {{ __('admin.readings.back') }}</a></p>
        <h1>{{ __('admin.readings.preview_title') }} #{{ $log->id }}</h1>

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
        </div>

        <div class="field">
            <label>{{ __('admin.readings.question') }}</label>
            <p>{{ $log->question }}</p>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.response') }}</label>
            <pre style="white-space:pre-wrap">{{ $log->response ?: '—' }}</pre>
        </div>

        <div class="field">
            <label>{{ __('admin.readings.drawn_cards') }}</label>
            @if (is_array($log->cards) && $log->cards !== [])
                <ul>
                    @foreach ($log->cards as $card)
                        <li>
                            <strong>{{ $card['slot_title'] ?? ($card['figure_name'] ?? $card['figure_id'] ?? '') }}</strong>
                            @if (! empty($card['figure_name']) && ! empty($card['slot_title']))
                                — {{ $card['figure_name'] }}
                            @endif
                            @if (! empty($card['astro_caption']))
                                · {{ $card['astro_caption'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted">—</p>
            @endif
        </div>
    </main>
@endsection

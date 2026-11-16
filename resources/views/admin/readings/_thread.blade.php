<div class="field">
    <label>{{ __('admin.readings.question') }}</label>
    <p>{{ $log->question }}</p>
</div>

@foreach ($thread as $turn)
    <div class="field">
        <label>{{ __('admin.readings.turn_'.$turn->turnKind()) }} · {{ $turn->created_at?->format('Y-m-d H:i') }}</label>
        @if ($turn->turnKind() === 'clarify' && filled(data_get($turn->astro, 'follow_up')))
            <p>{{ data_get($turn->astro, 'follow_up') }}</p>
        @endif
        @if ($turn->error)
            <pre style="white-space:pre-wrap">{{ $turn->error }}</pre>
        @endif
        <pre style="white-space:pre-wrap">{{ $turn->response ?: '—' }}</pre>
        @if (! empty($technical))
            <details>
                <summary>{{ __('admin.readings.prompts') }}</summary>
                <p class="muted">{{ $turn->sourceLabel() ?: ($turn->model ?: '—') }}</p>
                <pre style="white-space:pre-wrap">{{ $turn->system_prompt }}</pre>
                <pre style="white-space:pre-wrap">{{ $turn->user_prompt }}</pre>
            </details>
        @endif
    </div>
@endforeach

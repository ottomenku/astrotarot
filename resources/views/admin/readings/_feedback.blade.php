@php
    $hasFeedback = $session && (
        $session->feedback_rating !== null
        || filled($session->feedback_verdict)
        || filled($session->feedback_comment)
    );
@endphp
@if ($hasFeedback)
    <div class="field">
        <label>{{ __('admin.readings.feedback') }}</label>
        @if ($session->feedback_rating !== null)
            <p>{{ __('admin.readings.feedback_rating') }}: {{ $session->feedback_rating }}/5</p>
        @endif
        @if (filled($session->feedback_verdict))
            <p>{{ __('admin.readings.feedback_verdict') }}: {{ __('admin.readings.verdicts.'.$session->feedback_verdict) }}</p>
        @endif
        @if (filled($session->feedback_comment))
            <pre style="white-space:pre-wrap">{{ $session->feedback_comment }}</pre>
        @endif
    </div>
@endif

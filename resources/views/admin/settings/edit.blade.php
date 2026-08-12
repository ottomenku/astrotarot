@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.settings.title') }}</h1>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="min_question_chars">{{ __('admin.settings.min_question_chars') }}</label>
                <input
                    id="min_question_chars"
                    name="min_question_chars"
                    type="number"
                    min="1"
                    max="280"
                    value="{{ old('min_question_chars', $minQuestionChars) }}"
                    required
                >
                <p class="muted">{{ __('admin.settings.min_question_chars_hint') }}</p>
                @error('min_question_chars')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="field" style="border:0;padding:0;margin:0 0 1.25rem">
                <legend style="font-weight:600;margin-bottom:.5rem">{{ __('admin.settings.draw_mode') }}</legend>
                <p class="muted">{{ __('admin.settings.draw_mode_hint') }}</p>

                @foreach ([
                    \App\Services\AppSettings::DRAW_MODE_SCORING => 'draw_mode_scoring',
                    \App\Services\AppSettings::DRAW_MODE_RANDOM_POLARITY => 'draw_mode_random_polarity',
                ] as $value => $labelKey)
                    <label style="display:block;margin:.55rem 0">
                        <input
                            type="radio"
                            name="draw_mode"
                            value="{{ $value }}"
                            @checked(old('draw_mode', $drawMode) === $value)
                        >
                        <strong>{{ __('admin.settings.'.$labelKey) }}</strong>
                        <span class="muted"> — {{ __('admin.settings.'.$labelKey.'_hint') }}</span>
                    </label>
                @endforeach

                @error('draw_mode')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </fieldset>

            <button type="submit">{{ __('admin.settings.save') }}</button>
        </form>
    </main>
@endsection

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
                <legend style="font-weight:600;margin-bottom:.5rem">{{ __('admin.test_mode.site_section') }}</legend>
                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.55rem 0">
                    <input
                        type="checkbox"
                        name="site_enabled"
                        value="1"
                        @checked(old('site_enabled', $siteEnabled))
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.test_mode.site_enabled') }}</strong>
                        <span class="muted"> — {{ __('admin.test_mode.site_enabled_hint') }}</span>
                    </span>
                </label>
            </fieldset>

            <fieldset class="field" style="border:0;padding:0;margin:0 0 1.25rem">
                <legend style="font-weight:600;margin-bottom:.5rem">{{ __('admin.test_mode.registration_section') }}</legend>
                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.55rem 0">
                    <input
                        type="checkbox"
                        name="registration_enabled"
                        value="1"
                        @checked(old('registration_enabled', $registrationEnabled))
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.test_mode.registration_enabled') }}</strong>
                        <span class="muted"> — {{ __('admin.test_mode.registration_enabled_hint') }}</span>
                    </span>
                </label>
            </fieldset>

            <fieldset class="field" style="border:0;padding:0;margin:0 0 1.25rem">
                <legend style="font-weight:600;margin-bottom:.5rem">{{ __('admin.settings.paid_features') }}</legend>
                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.55rem 0">
                    <input
                        type="checkbox"
                        name="paid_features_enabled"
                        value="1"
                        @checked(old('paid_features_enabled', $paidFeaturesEnabled))
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.settings.paid_features_enabled') }}</strong>
                        <span class="muted"> — {{ __('admin.settings.paid_features_enabled_hint') }}</span>
                    </span>
                </label>
            </fieldset>

            <fieldset class="field" style="border:0;padding:0;margin:0 0 1.25rem">
                <legend style="font-weight:600;margin-bottom:.5rem">{{ __('admin.test_mode.llm_section') }}</legend>
                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.55rem 0">
                    <input
                        type="checkbox"
                        name="llm_enabled"
                        value="1"
                        @checked(old('llm_enabled', $llmEnabled))
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.test_mode.llm_enabled') }}</strong>
                        <span class="muted"> — {{ __('admin.test_mode.llm_enabled_hint') }}</span>
                    </span>
                </label>

                <p style="margin:1rem 0 .4rem"><strong>{{ __('admin.test_mode.llm_route') }}</strong></p>
                <p class="muted" style="margin:0 0 .75rem">{{ __('admin.test_mode.llm_route_hint') }}</p>

                @foreach ([
                    \App\Services\AppSettings::LLM_ROUTE_OLLAMA_SMALL => ['llm_route_small', $smallModel],
                    \App\Services\AppSettings::LLM_ROUTE_OLLAMA_LARGE => ['llm_route_large', $largeModel],
                    \App\Services\AppSettings::LLM_ROUTE_OPENAI => ['llm_route_openai', $cloudModel],
                ] as $value => [$labelKey, $model])
                    <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.45rem 0">
                        <input
                            type="radio"
                            name="llm_route"
                            value="{{ $value }}"
                            @checked(old('llm_route', $llmRoute) === $value)
                            style="width:auto;margin-top:.25rem"
                        >
                        <span>
                            <strong>{{ __('admin.test_mode.'.$labelKey, ['model' => $model]) }}</strong>
                            <span class="muted"> — {{ __('admin.test_mode.'.$labelKey.'_hint') }}</span>
                        </span>
                    </label>
                @endforeach
                @error('llm_route')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </fieldset>

            <button type="submit">{{ __('admin.settings.save') }}</button>
        </form>
    </main>
@endsection

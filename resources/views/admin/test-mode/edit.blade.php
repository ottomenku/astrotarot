@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.test_mode.title') }}</h1>
        <p class="muted">{{ __('admin.test_mode.hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.test-mode.update') }}">
            @csrf
            @method('PUT')

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

                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.45rem 0">
                    <input
                        type="radio"
                        name="llm_route"
                        value="{{ \App\Services\AppSettings::LLM_ROUTE_OLLAMA_SMALL }}"
                        @checked(old('llm_route', $llmRoute) === \App\Services\AppSettings::LLM_ROUTE_OLLAMA_SMALL)
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.test_mode.llm_route_small', ['model' => $smallModel]) }}</strong>
                        <span class="muted"> — {{ __('admin.test_mode.llm_route_small_hint') }}</span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.45rem 0">
                    <input
                        type="radio"
                        name="llm_route"
                        value="{{ \App\Services\AppSettings::LLM_ROUTE_OLLAMA_LARGE }}"
                        @checked(old('llm_route', $llmRoute) === \App\Services\AppSettings::LLM_ROUTE_OLLAMA_LARGE)
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.test_mode.llm_route_large', ['model' => $largeModel]) }}</strong>
                        <span class="muted"> — {{ __('admin.test_mode.llm_route_large_hint') }}</span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.45rem 0">
                    <input
                        type="radio"
                        name="llm_route"
                        value="{{ \App\Services\AppSettings::LLM_ROUTE_OPENAI }}"
                        @checked(old('llm_route', $llmRoute) === \App\Services\AppSettings::LLM_ROUTE_OPENAI)
                        style="width:auto;margin-top:.25rem"
                    >
                    <span>
                        <strong>{{ __('admin.test_mode.llm_route_openai', ['model' => $cloudModel]) }}</strong>
                        <span class="muted"> — {{ __('admin.test_mode.llm_route_openai_hint') }}</span>
                    </span>
                </label>
            </fieldset>

            <button type="submit">{{ __('admin.test_mode.save') }}</button>
        </form>
    </main>
@endsection

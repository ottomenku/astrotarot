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
            </fieldset>

            <button type="submit">{{ __('admin.test_mode.save') }}</button>
        </form>
    </main>
@endsection

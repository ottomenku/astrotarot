@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.prompts.title') }}</h1>
        <p class="muted">{{ __('admin.prompts.hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.prompts.update') }}" style="margin-bottom:1rem" data-lang-scope data-active-lang="{{ app()->getLocale() === 'en' ? 'en' : 'hu' }}">
            @csrf
            @method('PUT')
            @include('admin.partials.lang-switch')
            <div class="field" data-lang="hu">
                <label for="system_prompt_hu">{{ __('admin.prompts.system_prompt_hu') }}</label>
                <textarea id="system_prompt_hu" name="system_prompt_hu" rows="16" required>{{ old('system_prompt_hu', $systemPromptHu) }}</textarea>
                <p class="muted">{{ __('admin.prompts.system_hint') }}</p>
                @error('system_prompt_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="system_prompt_en">{{ __('admin.prompts.system_prompt_en') }}</label>
                <textarea id="system_prompt_en" name="system_prompt_en" rows="16" required>{{ old('system_prompt_en', $systemPromptEn) }}</textarea>
                @error('system_prompt_en')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field" data-lang="hu">
                <label for="base_prompt_hu">{{ __('admin.prompts.base_prompt_hu') }}</label>
                <textarea id="base_prompt_hu" name="base_prompt_hu" rows="12" required>{{ old('base_prompt_hu', $basePromptHu) }}</textarea>
                <p class="muted">{{ __('admin.prompts.base_hint') }}</p>
                @error('base_prompt_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="base_prompt_en">{{ __('admin.prompts.base_prompt_en') }}</label>
                <textarea id="base_prompt_en" name="base_prompt_en" rows="12" required>{{ old('base_prompt_en', $basePromptEn) }}</textarea>
                @error('base_prompt_en')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit">{{ __('admin.prompts.save') }}</button>
        </form>

        <form method="POST" action="{{ route('admin.prompts.reset') }}" onsubmit="return confirm(@json(__('admin.prompts.reset_confirm')))">
            @csrf
            <button type="submit">{{ __('admin.prompts.reset_button') }}</button>
        </form>
    </main>
@endsection

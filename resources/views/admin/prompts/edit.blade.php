@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.prompts.title') }}</h1>
        <p class="muted">{{ __('admin.prompts.hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.prompts.update') }}" style="margin-bottom:1rem">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="system_prompt_hu">{{ __('admin.prompts.system_prompt_hu') }}</label>
                <textarea id="system_prompt_hu" name="system_prompt_hu" rows="12" required>{{ old('system_prompt_hu', $systemPromptHu) }}</textarea>
                @error('system_prompt_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="system_prompt_en">{{ __('admin.prompts.system_prompt_en') }}</label>
                <textarea id="system_prompt_en" name="system_prompt_en" rows="12" required>{{ old('system_prompt_en', $systemPromptEn) }}</textarea>
                @error('system_prompt_en')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="clarify_prompt_hu">{{ __('admin.prompts.clarify_prompt_hu') }}</label>
                <textarea id="clarify_prompt_hu" name="clarify_prompt_hu" rows="8" required>{{ old('clarify_prompt_hu', $clarifyPromptHu) }}</textarea>
                @error('clarify_prompt_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="clarify_prompt_en">{{ __('admin.prompts.clarify_prompt_en') }}</label>
                <textarea id="clarify_prompt_en" name="clarify_prompt_en" rows="8" required>{{ old('clarify_prompt_en', $clarifyPromptEn) }}</textarea>
                @error('clarify_prompt_en')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="expand_prompt_hu">{{ __('admin.prompts.expand_prompt_hu') }}</label>
                <textarea id="expand_prompt_hu" name="expand_prompt_hu" rows="8" required>{{ old('expand_prompt_hu', $expandPromptHu) }}</textarea>
                @error('expand_prompt_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="expand_prompt_en">{{ __('admin.prompts.expand_prompt_en') }}</label>
                <textarea id="expand_prompt_en" name="expand_prompt_en" rows="8" required>{{ old('expand_prompt_en', $expandPromptEn) }}</textarea>
                @error('expand_prompt_en')
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

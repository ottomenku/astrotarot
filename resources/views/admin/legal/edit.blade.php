@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.legal.title') }}</h1>
        <p class="muted">{{ __('admin.legal.hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('admin.legal.update') }}" data-lang-scope data-active-lang="{{ app()->getLocale() === 'en' ? 'en' : 'hu' }}">
            @csrf
            @method('PUT')
            @include('admin.partials.lang-switch')
            <div class="field" data-lang="hu">
                <label for="terms_hu">{{ __('admin.legal.terms_hu') }}</label>
                <textarea id="terms_hu" name="terms_hu" rows="12" required>{{ old('terms_hu', $termsHu) }}</textarea>
                @error('terms_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="terms_en">{{ __('admin.legal.terms_en') }}</label>
                <textarea id="terms_en" name="terms_en" rows="12" required>{{ old('terms_en', $termsEn) }}</textarea>
                @error('terms_en')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field" data-lang="hu">
                <label for="privacy_hu">{{ __('admin.legal.privacy_hu') }}</label>
                <textarea id="privacy_hu" name="privacy_hu" rows="12" required>{{ old('privacy_hu', $privacyHu) }}</textarea>
                @error('privacy_hu')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="privacy_en">{{ __('admin.legal.privacy_en') }}</label>
                <textarea id="privacy_en" name="privacy_en" rows="12" required>{{ old('privacy_en', $privacyEn) }}</textarea>
                @error('privacy_en')
                    <p class="muted" style="color:#b33">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit">{{ __('admin.legal.save') }}</button>
        </form>
    </main>
@endsection

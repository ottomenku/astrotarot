@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.pack_install.title') }}</h1>
        <p class="muted">{{ __('admin.pack_install.hint') }}</p>

        <form method="POST" action="{{ route('admin.packs.install.store') }}" enctype="multipart/form-data" data-lang-scope data-active-lang="{{ app()->getLocale() === 'en' ? 'en' : 'hu' }}">
            @csrf
            @include('admin.partials.lang-switch')

            <div class="row">
                <div class="field">
                    <label for="slug">{{ __('admin.pack_install.slug') }}</label>
                    <input id="slug" name="slug" value="{{ old('slug') }}" placeholder="uj-pakli" required>
                    @error('slug') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="card_count">{{ __('admin.pack_install.card_count') }}</label>
                    <input id="card_count" name="card_count" type="number" min="1" max="500" value="{{ old('card_count', 1) }}" required>
                    @error('card_count') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="row">
                <div class="field" data-lang="hu">
                    <label for="name_hu">{{ __('admin.pack_install.name_hu') }}</label>
                    <input id="name_hu" name="name_hu" value="{{ old('name_hu') }}" required>
                    @error('name_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field" data-lang="en">
                    <label for="name_en">{{ __('admin.pack_install.name_en') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en') }}" required>
                    @error('name_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field" data-lang="hu">
                <label for="description_hu">{{ __('admin.pack_install.description_hu') }}</label>
                <textarea id="description_hu" name="description_hu" rows="3">{{ old('description_hu') }}</textarea>
                @error('description_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="description_en">{{ __('admin.pack_install.description_en') }}</label>
                <textarea id="description_en" name="description_en" rows="3">{{ old('description_en') }}</textarea>
                @error('description_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <div class="row">
                <div class="field">
                    <label for="type">{{ __('admin.pack_install.type') }}</label>
                    <select id="type" name="type" required>
                        @foreach (\App\Models\CardPack::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type', 'tarot') === $type)>{{ __('admin.packs.type_'.$type) }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="archive">{{ __('admin.pack_install.archive') }}</label>
                    <input id="archive" name="archive" type="file" accept=".zip,application/zip" required>
                    <p class="muted">{{ __('admin.pack_install.archive_hint') }}</p>
                    @error('archive') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <p style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button type="submit">{{ __('admin.pack_install.submit') }}</button>
                <a href="{{ route('admin.packs.index') }}">{{ __('admin.packs.back') }}</a>
            </p>
        </form>
    </main>
@endsection

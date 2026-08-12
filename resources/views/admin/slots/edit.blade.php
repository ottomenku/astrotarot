@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.slots.title') }}</h1>
        <p class="muted">{{ __('admin.slots.hint') }}</p>
        <ul class="muted" style="margin:0 0 1.25rem;padding-left:1.2rem">
            <li>{{ __('admin.slots.source_sun_sign') }} — {{ __('admin.slots.source_sun_sign_hint') }}</li>
            <li>{{ __('admin.slots.source_element') }} — {{ __('admin.slots.source_element_hint') }}</li>
            <li>{{ __('admin.slots.source_modality') }} — {{ __('admin.slots.source_modality_hint') }}</li>
            <li>{{ __('admin.slots.source_random1') }} — {{ __('admin.slots.source_random1_hint') }}</li>
            <li>{{ __('admin.slots.source_full_random') }} — {{ __('admin.slots.source_full_random_hint') }}</li>
        </ul>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        @error('slots')
            <p class="muted" style="color:#b33">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('admin.slots.update') }}">
            @csrf
            @method('PUT')

            @for ($i = 0; $i < 3; $i++)
                <div class="field">
                    <label for="slot_{{ $i }}">{{ __('admin.slots.slot', ['n' => $i + 1]) }}</label>
                    <select id="slot_{{ $i }}" name="slots[{{ $i }}]" required>
                        @foreach ($sources as $source)
                            <option
                                value="{{ $source }}"
                                @selected(old('slots.'.$i, $allocation[$i] ?? null) === $source)
                            >
                                {{ __('admin.slots.source_'.$source) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endfor

            <button type="submit">{{ __('admin.slots.save') }}</button>
        </form>
    </main>
@endsection

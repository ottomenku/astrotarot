@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    @php
        $type = old('type', $pack->type ?: 'tarot');
        $isFigure = in_array($type, ['tarot', 'oracle'], true);
    @endphp

    <main class="card">
        <h1>{{ $pack->exists ? __('admin.packs.edit') : __('admin.packs.create') }}</h1>
        <p class="muted">{{ __('admin.packs.form_hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ $pack->exists ? route('admin.packs.update', $pack) : route('admin.packs.store') }}" enctype="multipart/form-data" data-pack-form>
            @csrf
            @if ($pack->exists)
                @method('PUT')
            @endif

            <div class="row">
                <div class="field">
                    <label for="name_hu">{{ __('admin.packs.name_hu') }}</label>
                    <input id="name_hu" name="name_hu" value="{{ old('name_hu', $pack->name_hu) }}" required>
                    @error('name_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="name_en">{{ __('admin.packs.name_en') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $pack->name_en) }}" required>
                    @error('name_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="slug">{{ __('admin.packs.slug') }}</label>
                    <input id="slug" name="slug" value="{{ old('slug', $pack->slug) }}">
                    <p class="muted">{{ __('admin.packs.slug_hint') }}</p>
                    @error('slug') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="type">{{ __('admin.packs.type') }}</label>
                    <select id="type" name="type" data-pack-type-field required>
                        <option value="tarot" @selected($type === 'tarot')>{{ __('admin.packs.type_tarot') }}</option>
                        <option value="oracle" @selected($type === 'oracle')>{{ __('admin.packs.type_oracle') }}</option>
                        <option value="card_back" @selected($type === 'card_back')>{{ __('admin.packs.type_card_back') }}</option>
                    </select>
                </div>
                <div class="field">
                    <label for="sort_order">{{ __('admin.packs.sort_order') }}</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $pack->sort_order ?? 0) }}" required>
                </div>
            </div>

            <div class="field">
                <label for="description_hu">{{ __('admin.packs.description_hu') }}</label>
                <textarea id="description_hu" name="description_hu" rows="2">{{ old('description_hu', $pack->description_hu) }}</textarea>
            </div>
            <div class="field">
                <label for="description_en">{{ __('admin.packs.description_en') }}</label>
                <textarea id="description_en" name="description_en" rows="2">{{ old('description_en', $pack->description_en) }}</textarea>
            </div>

            <div class="row">
                <div class="field">
                    <label for="card_count">{{ __('admin.packs.cards') }}</label>
                    <input id="card_count" name="card_count" type="number" min="0" max="200" value="{{ old('card_count', $pack->card_count ?? 0) }}">
                </div>
                <div class="field">
                    <label for="style">{{ __('admin.packs.style') }}</label>
                    <input id="style" name="style" value="{{ old('style', $pack->style) }}">
                </div>
                <div class="field">
                    <label for="owner">{{ __('admin.packs.owner') }}</label>
                    <input id="owner" name="owner" value="{{ old('owner', $pack->owner) }}">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $pack->is_default))>
                        {{ __('admin.packs.is_default') }}
                    </label>
                    <p class="muted">{{ __('admin.packs.is_default_hint') }}</p>
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pack->is_active))>
                        {{ __('admin.packs.active') }}
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="preview">{{ __('admin.packs.preview') }}</label>
                @if ($pack->previewUrl())
                    <p><img src="{{ $pack->previewUrl() }}" alt="" width="72" height="96" style="object-fit:cover;border-radius:.35rem"></p>
                @endif
                <input id="preview" name="preview" type="file" accept="image/png,image/jpeg,image/webp">
            </div>

            <div class="field" data-asset-wrap>
                <label for="asset">{{ __('admin.packs.asset') }}</label>
                <input id="asset" name="asset" type="file" accept="image/png,image/jpeg,image/webp">
                <p class="muted">{{ __('admin.packs.asset_hint') }}</p>
            </div>

            <div data-figure-defaults>
                <h2>{{ __('admin.packs.skins') }}</h2>
                <p class="muted">{{ __('admin.packs.skins_hint') }}</p>
                <div class="row">
                    <div class="field">
                        <label for="meaning_group_id">{{ __('admin.packs.meaning_group') }}</label>
                        <select id="meaning_group_id" name="meaning_group_id" data-meaning-group-field>
                            <option value="">—</option>
                            @foreach ($meaningGroups as $group)
                                <option
                                    value="{{ $group->id }}"
                                    data-pack-type="{{ $group->type }}"
                                    @selected((int) old('meaning_group_id', $pack->meaning_group_id) === $group->id)
                                >{{ $group->name_hu }}</option>
                            @endforeach
                        </select>
                        <p class="muted">{{ __('admin.packs.meaning_group_hint') }}</p>
                    </div>
                    <div class="field">
                        <label for="default_card_back_id">{{ __('admin.packs.default_back') }}</label>
                        <select id="default_card_back_id" name="default_card_back_id">
                            <option value="">—</option>
                            @foreach ($backs as $back)
                                <option value="{{ $back->id }}" @selected((int) old('default_card_back_id', $pack->default_card_back_id) === $back->id)>{{ $back->name_hu }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <p style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button type="submit">{{ __('admin.packs.save') }}</button>
                <a href="{{ route('admin.packs.index') }}">{{ __('admin.packs.back') }}</a>
            </p>
        </form>
        @if ($pack->exists)
            <form method="POST" action="{{ route('admin.packs.destroy', $pack) }}" onsubmit="return confirm(@json(__('admin.packs.delete_confirm')))" style="margin-top:1rem">
                @csrf
                @method('DELETE')
                <button type="submit">{{ __('admin.packs.delete') }}</button>
            </form>
        @endif
    </main>

    <script>
        (() => {
            const type = document.querySelector('[data-pack-type-field]');
            const asset = document.querySelector('[data-asset-wrap]');
            const skins = document.querySelector('[data-figure-defaults]');
            const meaning = document.querySelector('[data-meaning-group-field]');
            const sync = () => {
                const value = type?.value;
                const figure = value === 'tarot' || value === 'oracle';
                if (asset) asset.hidden = figure;
                if (skins) skins.hidden = !figure;
                if (meaning) {
                    let keep = false;
                    meaning.querySelectorAll('option[data-pack-type]').forEach((option) => {
                        const match = option.getAttribute('data-pack-type') === value;
                        option.hidden = !match;
                        if (match && option.selected) keep = true;
                    });
                    if (!keep) meaning.value = '';
                }
            };
            type?.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection

@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    @php
        $rules = old('rules', $rules ?? []);
        $rules = is_array($rules) ? array_values($rules) : [];
        if ($rules === []) {
            $rules[] = [
                'pack_type' => 'oracle',
                'default_pack_id' => '',
                'title_hu' => '',
                'title_en' => '',
                'info_mode' => 'astro',
                'row' => 1,
                'place' => 1,
                'draw_order' => 1,
            ];
        }
    @endphp

    <main class="card">
        <h1>{{ $game->exists ? __('admin.games.edit') : __('admin.games.create') }}</h1>
        <p class="muted">{{ __('admin.games.form_hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ $game->exists ? route('admin.games.update', $game) : route('admin.games.store') }}" enctype="multipart/form-data" data-lang-scope data-active-lang="{{ app()->getLocale() === 'en' ? 'en' : 'hu' }}">
            @csrf
            @if ($game->exists)
                @method('PUT')
            @endif
            @include('admin.partials.lang-switch')

            <div class="row">
                <div class="field" data-lang="hu">
                    <label for="name_hu">{{ __('admin.games.name_hu') }}</label>
                    <input id="name_hu" name="name_hu" value="{{ old('name_hu', $game->name_hu) }}" required>
                    @error('name_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field" data-lang="en">
                    <label for="name_en">{{ __('admin.games.name_en') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $game->name_en) }}" required>
                    @error('name_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="slug">{{ __('admin.games.slug') }}</label>
                    <input id="slug" name="slug" value="{{ old('slug', $game->slug) }}" placeholder="astro-tarot">
                    <p class="muted">{{ __('admin.games.slug_hint') }}</p>
                    @error('slug') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="sort_order">{{ __('admin.games.sort_order') }}</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $game->sort_order ?? 0) }}" required>
                </div>
                <div class="field">
                    <label for="category_order">{{ __('admin.games.category_order') }}</label>
                    <input id="category_order" name="category_order" type="number" min="1" max="10" value="{{ old('category_order', $game->category_order ?? 1) }}" required>
                    <p class="muted">{{ __('admin.games.category_order_hint') }}</p>
                    @error('category_order') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field" data-lang="hu">
                <label for="description_hu">{{ __('admin.games.description_hu') }}</label>
                <textarea id="description_hu" name="description_hu" rows="3">{{ old('description_hu', $game->description_hu) }}</textarea>
                @error('description_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="description_en">{{ __('admin.games.description_en') }}</label>
                <textarea id="description_en" name="description_en" rows="3">{{ old('description_en', $game->description_en) }}</textarea>
                @error('description_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="icon">{{ __('admin.games.icon') }}</label>
                @if ($game->iconUrl())
                    <p><img src="{{ $game->iconUrl() }}" alt="" width="48" height="48" style="object-fit:cover;border-radius:.35rem"></p>
                @endif
                <input id="icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp">
                <p class="muted">{{ __('admin.games.icon_hint') }}</p>
                @error('icon') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <div class="row">
                <div class="field">
                    <label>
                        <input type="checkbox" name="has_question" value="1" @checked(old('has_question', $game->has_question))>
                        {{ __('admin.games.has_question') }}
                    </label>
                    <p class="muted">{{ __('admin.games.has_question_hint') }}</p>
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $game->is_default))>
                        {{ __('admin.games.is_default') }}
                    </label>
                    <p class="muted">{{ __('admin.games.is_default_hint') }}</p>
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $game->is_active))>
                        {{ __('admin.games.active') }}
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="light_shadow_mode">{{ __('admin.games.light_shadow_mode') }}</label>
                <select id="light_shadow_mode" name="light_shadow_mode">
                    @foreach ([
                        'full-astro' => 'light_shadow_full_astro',
                        'oracle-astro' => 'light_shadow_oracle_astro',
                        'full-random' => 'light_shadow_full_random',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('light_shadow_mode', $game->light_shadow_mode ?: 'full-random') === $value)>{{ __('admin.games.'.$label) }}</option>
                    @endforeach
                </select>
                <p class="muted">{{ __('admin.games.light_shadow_hint') }}</p>
            </div>

            <fieldset class="field" data-astro-modes>
                <legend>{{ __('admin.games.astro_data_mode') }}</legend>
                @php
                    $selectedModes = old('astro_data_modes', $game->astroModes());
                    $selectedModes = is_array($selectedModes) ? $selectedModes : [];
                @endphp
                <label>
                    <input type="checkbox" name="astro_data_modes[]" value="none" data-astro-none @checked($selectedModes === [])>
                    {{ __('admin.games.astro_data_none') }}
                </label>
                @foreach ([
                    'now' => 'astro_data_now',
                    'personal' => 'astro_data_personal',
                    'relations' => 'astro_data_relations',
                    'projections' => 'astro_data_projections',
                ] as $value => $label)
                    <label>
                        <input type="checkbox" name="astro_data_modes[]" value="{{ $value }}" data-astro-send @checked(in_array($value, $selectedModes, true))>
                        {{ __('admin.games.'.$label) }}
                    </label>
                @endforeach
                <p class="muted">{{ __('admin.games.astro_data_hint') }}</p>
            </fieldset>
            <script>
                document.querySelectorAll('[data-astro-modes]').forEach((box) => {
                    const none = box.querySelector('[data-astro-none]');
                    const sends = [...box.querySelectorAll('[data-astro-send]')];
                    none?.addEventListener('change', () => {
                        if (none.checked) sends.forEach((input) => { input.checked = false; });
                    });
                    sends.forEach((input) => input.addEventListener('change', () => {
                        if (input.checked && none) none.checked = false;
                        if (none && sends.every((item) => !item.checked)) none.checked = true;
                    }));
                });
            </script>

            <fieldset class="field">
                <legend>{{ __('admin.games.spread_types') }}</legend>
                @php
                    $selectedTypes = old('spread_types', $game->spread_types ?? []);
                    $selectedTypes = is_array($selectedTypes) ? $selectedTypes : [];
                @endphp
                @foreach ([
                    'BINARY' => 'category_binary',
                    'CHOICE' => 'category_choice',
                    'TIMING' => 'category_timing',
                    'EXPLORATORY' => 'category_exploratory',
                    'FORECAST' => 'category_forecast',
                    'ANALYSIS' => 'category_analysis',
                    'RELATION' => 'category_relation',
                    'N/A' => 'category_na',
                ] as $value => $label)
                    <label style="display:block;margin:.25rem 0">
                        <input type="checkbox" name="spread_types[]" value="{{ $value }}" @checked(in_array($value, $selectedTypes, true))>
                        {{ __('admin.games.'.$label) }}
                    </label>
                @endforeach
                <p class="muted">{{ __('admin.games.spread_types_hint') }}</p>
            </fieldset>

            <fieldset class="field">
                <legend>{{ __('admin.games.spread_dimensions') }}</legend>
                @php
                    $selectedDimensions = old('spread_dimensions', $game->spread_dimensions ?? []);
                    $selectedDimensions = is_array($selectedDimensions) ? $selectedDimensions : [];
                @endphp
                @foreach ([
                    'REL' => 'dimension_rel',
                    'SOC' => 'dimension_soc',
                    'CAR' => 'dimension_car',
                    'FIN' => 'dimension_fin',
                    'HLT' => 'dimension_hlt',
                    'LEG' => 'dimension_leg',
                    'SPL' => 'dimension_spl',
                    'N/A' => 'dimension_na',
                ] as $value => $label)
                    <label style="display:block;margin:.25rem 0">
                        <input type="checkbox" name="spread_dimensions[]" value="{{ $value }}" @checked(in_array($value, $selectedDimensions, true))>
                        {{ __('admin.games.'.$label) }}
                    </label>
                @endforeach
                <p class="muted">{{ __('admin.games.spread_dimensions_hint') }}</p>
            </fieldset>

            <div class="field" data-lang="hu">
                <label for="prompt_hu">{{ __('admin.games.prompt_hu') }}</label>
                <textarea id="prompt_hu" name="prompt_hu" rows="8">{{ old('prompt_hu', $game->prompt_hu) }}</textarea>
                <p class="muted">{{ __('admin.games.prompt_hint') }}</p>
                @error('prompt_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="prompt_en">{{ __('admin.games.prompt_en') }}</label>
                <textarea id="prompt_en" name="prompt_en" rows="8">{{ old('prompt_en', $game->prompt_en) }}</textarea>
                @error('prompt_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <h2>{{ __('admin.games.rules') }}</h2>
            @include('admin.partials.lang-switch')
            <p class="muted">{{ __('admin.games.rules_hint') }}</p>
            @error('rules') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror

            <div data-rules>
                @foreach ($rules as $index => $rule)
                    @include('admin.games.partials.rule-row', ['index' => $index, 'rule' => $rule, 'packs' => $packs, 'backs' => $backs])
                @endforeach
            </div>
            <p>
                <button type="button" data-add-rule>{{ __('admin.games.add_rule') }}</button>
            </p>

            <p style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button type="submit">{{ __('admin.games.save') }}</button>
                <a href="{{ route('admin.games.index') }}">{{ __('admin.games.back') }}</a>
            </p>
        </form>
        @if ($game->exists)
            <form method="POST" action="{{ route('admin.games.destroy', $game) }}" onsubmit="return confirm(@json(__('admin.games.delete_confirm')))" style="margin-top:1rem">
                @csrf
                @method('DELETE')
                <button type="submit">{{ __('admin.games.delete') }}</button>
            </form>
        @endif
    </main>

    <template data-rule-template>
        @include('admin.games.partials.rule-row', [
            'index' => '__INDEX__',
            'rule' => [
                'pack_type' => 'oracle',
                'default_pack_id' => '',
                'card_back_id' => '',
                'slot_key' => '',
                'title_hu' => '',
                'title_en' => '',
                'description_hu' => '',
                'description_en' => '',
                'info_mode' => '',
                'celestial_helper' => 'none',
                'light_shadow' => 'none',
                'row' => 1,
                'place' => 1,
                'draw_order' => 1,
            ],
            'packs' => $packs,
            'backs' => $backs,
        ])
    </template>

    <script>
        (() => {
            const list = document.querySelector('[data-rules]');
            const template = document.querySelector('[data-rule-template]');
            const add = document.querySelector('[data-add-rule]');
            if (!list || !template || !add) return;

            const nextIndex = () => list.querySelectorAll('[data-rule-row]').length;

            const filterPacks = (row) => {
                const type = row.querySelector('[data-pack-type]')?.value;
                row.querySelectorAll('[data-default-pack] option[data-type]').forEach((option) => {
                    const match = !type || option.getAttribute('data-type') === type;
                    option.hidden = !match;
                    option.disabled = !match;
                    if (!match && option.selected) option.selected = false;
                });
            };

            list.querySelectorAll('[data-rule-row]').forEach(filterPacks);
            list.addEventListener('change', (event) => {
                const typeSelect = event.target.closest('[data-pack-type]');
                if (!typeSelect) return;
                const row = typeSelect.closest('[data-rule-row]');
                if (row) filterPacks(row);
            });

            add.addEventListener('click', () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex()));
                list.insertAdjacentHTML('beforeend', html);
                const rows = list.querySelectorAll('[data-rule-row]');
                const row = rows[rows.length - 1];
                const draw = row?.querySelector('[data-draw-order]');
                if (draw) draw.value = String(rows.length);
                filterPacks(row);
            });

            const removeConfirm = @json(__('admin.games.remove_rule_confirm'));
            list.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-rule]');
                if (!button) return;
                const row = button.closest('[data-rule-row]');
                if (!row || list.querySelectorAll('[data-rule-row]').length < 2) return;
                if (!window.confirm(removeConfirm)) return;
                row.remove();
            });
        })();
    </script>
@endsection

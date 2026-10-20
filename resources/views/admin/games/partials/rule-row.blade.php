@php
    $rule = is_array($rule ?? null) ? $rule : [];
    $index = $index ?? 0;
    $packs = is_array($packs ?? null) ? $packs : [];
    $backs = is_array($backs ?? null) ? $backs : [];
    $selectedType = $rule['pack_type'] ?? '';
    if ($selectedType === '' && ($rule['pack_slug'] ?? '') !== '') {
        $match = collect($packs)->firstWhere('slug', $rule['pack_slug']);
        $selectedType = is_array($match) ? ($match['type'] ?? '') : '';
    }
    if ($selectedType === '') {
        $selectedType = $packs[0]['type'] ?? 'tarot';
    }
    $selectedPackId = (string) ($rule['default_pack_id'] ?? '');
    if ($selectedPackId === '' && ($rule['pack_slug'] ?? '') !== '') {
        $match = collect($packs)->firstWhere('slug', $rule['pack_slug']);
        $selectedPackId = is_array($match) ? (string) ($match['id'] ?? '') : '';
    }
    $selectedBackId = (string) ($rule['card_back_id'] ?? '');
@endphp
<div class="rule-card" data-rule-row>
    <button type="button" class="admin-icon-btn is-red rule-remove" data-remove-rule title="{{ __('admin.games.remove_rule') }}" aria-label="{{ __('admin.games.remove_rule') }}">{!! \App\Support\AdminIcons::trash() !!}</button>
    <div class="row">
        <div class="field">
            <label>{{ __('admin.games.draw_order') }}</label>
            <input type="number" name="rules[{{ $index }}][draw_order]" data-draw-order min="1" max="49" value="{{ $rule['draw_order'] ?? ((int) $index + 1) }}" required>
        </div>
        <div class="field">
            <label>{{ __('admin.games.place') }}</label>
            <input type="number" name="rules[{{ $index }}][place]" min="1" max="7" value="{{ $rule['place'] ?? 1 }}" required>
        </div>
        <div class="field" data-lang="hu">
            <label>{{ __('admin.games.slot_title_hu') }}</label>
            <input name="rules[{{ $index }}][title_hu]" value="{{ $rule['title_hu'] ?? '' }}" required>
        </div>
        <div class="field" data-lang="en">
            <label>{{ __('admin.games.slot_title_en') }}</label>
            <input name="rules[{{ $index }}][title_en]" value="{{ $rule['title_en'] ?? '' }}" required>
        </div>
    </div>
    <div class="row">
        <div class="field">
            <label>{{ __('admin.games.slot_key') }}</label>
            <input name="rules[{{ $index }}][slot_key]" value="{{ $rule['slot_key'] ?? '' }}" maxlength="64">
        </div>
        <div class="field">
            <label>{{ __('admin.games.row') }}</label>
            <input type="number" name="rules[{{ $index }}][row]" min="1" max="12" value="{{ $rule['row'] ?? 1 }}" required>
        </div>
        <div class="field">
            <label>{{ __('admin.games.pack_type') }}</label>
            <select name="rules[{{ $index }}][pack_type]" data-pack-type required>
                <option value="tarot" @selected($selectedType === 'tarot')>{{ __('admin.packs.type_tarot') }}</option>
                <option value="oracle" @selected($selectedType === 'oracle')>{{ __('admin.packs.type_oracle') }}</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="field">
            <label>{{ __('admin.games.default_pack') }}</label>
            <select name="rules[{{ $index }}][default_pack_id]" data-default-pack>
                <option value="">{{ __('admin.games.default_pack_category') }}</option>
                @foreach ($packs as $pack)
                    <option value="{{ $pack['id'] }}" data-type="{{ $pack['type'] }}" @selected($selectedPackId === (string) $pack['id'])>
                        {{ $pack['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>{{ __('admin.games.card_back') }}</label>
            <select name="rules[{{ $index }}][card_back_id]">
                <option value="">{{ __('admin.games.card_back_default') }}</option>
                @foreach ($backs as $back)
                    <option value="{{ $back['id'] }}" @selected($selectedBackId === (string) $back['id'])>
                        {{ $back['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="field" data-lang="hu">
        <label>{{ __('admin.games.slot_description_hu') }}</label>
        <textarea name="rules[{{ $index }}][description_hu]" rows="2">{{ $rule['description_hu'] ?? '' }}</textarea>
    </div>
    <div class="field" data-lang="en">
        <label>{{ __('admin.games.slot_description_en') }}</label>
        <textarea name="rules[{{ $index }}][description_en]" rows="2">{{ $rule['description_en'] ?? '' }}</textarea>
    </div>
    <input type="hidden" name="rules[{{ $index }}][info_mode]" value="{{ $rule['info_mode'] ?? '' }}">
    @error('rules.'.$index.'.place') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
    @error('rules.'.$index.'.draw_order') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
    @error('rules.'.$index.'.pack_type') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
    @error('rules.'.$index.'.slot_key') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
    @error('rules.'.$index.'.card_back_id') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
</div>

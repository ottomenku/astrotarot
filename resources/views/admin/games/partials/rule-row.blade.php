@php
    $rule = is_array($rule ?? null) ? $rule : [];
    $index = $index ?? 0;
    $packs = is_array($packs ?? null) ? $packs : [];
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
@endphp
<div class="rule-card" data-rule-row>
    <div class="row">
        <div class="field">
            <label>{{ __('admin.games.pack_type') }}</label>
            <select name="rules[{{ $index }}][pack_type]" data-pack-type required>
                <option value="tarot" @selected($selectedType === 'tarot')>{{ __('admin.packs.type_tarot') }}</option>
                <option value="oracle" @selected($selectedType === 'oracle')>{{ __('admin.packs.type_oracle') }}</option>
            </select>
        </div>
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
            <p class="muted">{{ __('admin.games.default_pack_hint') }}</p>
        </div>
        <div class="field">
            <label>{{ __('admin.games.info_mode') }}</label>
            <select name="rules[{{ $index }}][info_mode]" required>
                <option value="astro" @selected(($rule['info_mode'] ?? '') === 'astro')>{{ __('admin.games.info_astro') }}</option>
                <option value="meaning" @selected(($rule['info_mode'] ?? '') === 'meaning')>{{ __('admin.games.info_meaning') }}</option>
                <option value="none" @selected(($rule['info_mode'] ?? '') === 'none')>{{ __('admin.games.info_none') }}</option>
            </select>
        </div>
        <div class="field">
            <label>{{ __('admin.games.row') }}</label>
            <input type="number" name="rules[{{ $index }}][row]" min="1" max="12" value="{{ $rule['row'] ?? 1 }}" required>
        </div>
        <div class="field">
            <label>{{ __('admin.games.place') }}</label>
            <input type="number" name="rules[{{ $index }}][place]" min="1" max="7" value="{{ $rule['place'] ?? 1 }}" required>
        </div>
    </div>
    <div class="row">
        <div class="field">
            <label>{{ __('admin.games.slot_title_hu') }}</label>
            <input name="rules[{{ $index }}][title_hu]" value="{{ $rule['title_hu'] ?? '' }}" required>
        </div>
        <div class="field">
            <label>{{ __('admin.games.slot_title_en') }}</label>
            <input name="rules[{{ $index }}][title_en]" value="{{ $rule['title_en'] ?? '' }}" required>
        </div>
    </div>
    <p><button type="button" data-remove-rule>{{ __('admin.games.remove_rule') }}</button></p>
    @error('rules.'.$index.'.place') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
    @error('rules.'.$index.'.pack_type') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
</div>

@php
    $rule = is_array($rule ?? null) ? $rule : [];
    $index = $index ?? 0;
@endphp
<div class="rule-card" data-rule-row>
    <div class="row">
        <div class="field">
            <label>{{ __('admin.games.pack') }}</label>
            <select name="rules[{{ $index }}][pack_slug]" required>
                @foreach ($packs as $slug)
                    <option value="{{ $slug }}" @selected(($rule['pack_slug'] ?? '') === $slug)>{{ $slug }}</option>
                @endforeach
            </select>
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
    @error('rules.'.$index.'.pack_slug') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
</div>

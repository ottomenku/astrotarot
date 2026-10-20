@php
    $entry = is_array($entry ?? null) ? $entry : [];
@endphp
<div class="rule-card" data-entry-row="{{ $index }}">
    <input type="hidden" name="entries[{{ $index }}][id]" value="{{ $entry['id'] ?? '' }}">
    <div class="row">
        <div class="field">
            <label>{{ __('admin.meaning_groups.figure_id') }}</label>
            <input name="entries[{{ $index }}][figure_id]" value="{{ $entry['figure_id'] ?? '' }}" placeholder="00-the-fool">
        </div>
        <div class="field" data-lang="hu">
            <label>{{ __('admin.meaning_groups.card_name_hu') }}</label>
            <input name="entries[{{ $index }}][name_hu]" value="{{ $entry['name_hu'] ?? '' }}">
        </div>
        <div class="field" data-lang="en">
            <label>{{ __('admin.meaning_groups.card_name_en') }}</label>
            <input name="entries[{{ $index }}][name_en]" value="{{ $entry['name_en'] ?? '' }}">
        </div>
        <div class="field" style="align-self:end">
            <button type="button" data-remove-entry>{{ __('admin.meaning_groups.remove_entry') }}</button>
        </div>
    </div>
    <div class="row">
        <div class="field" data-lang="hu">
            <label>{{ __('admin.meaning_groups.meaning_hu') }}</label>
            <input name="entries[{{ $index }}][meaning_hu]" value="{{ $entry['meaning_hu'] ?? '' }}">
        </div>
        <div class="field" data-lang="en">
            <label>{{ __('admin.meaning_groups.meaning_en') }}</label>
            <input name="entries[{{ $index }}][meaning_en]" value="{{ $entry['meaning_en'] ?? '' }}">
        </div>
    </div>
    <div class="field" data-lang="hu">
        <label>{{ __('admin.meaning_groups.card_description_hu') }}</label>
        <textarea name="entries[{{ $index }}][description_hu]" rows="2">{{ $entry['description_hu'] ?? '' }}</textarea>
    </div>
    <div class="field" data-lang="en">
        <label>{{ __('admin.meaning_groups.card_description_en') }}</label>
        <textarea name="entries[{{ $index }}][description_en]" rows="2">{{ $entry['description_en'] ?? '' }}</textarea>
    </div>
</div>

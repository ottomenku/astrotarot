<div class="place-combo" data-place-combo data-kind="{{ $kind }}" data-pair="{{ $pair }}" @if ($code) data-code="{{ $code }}" @endif data-selected="{{ $value }}">
    <label class="credits-note">
        <span>{{ $label }}</span>
        <input
            type="text"
            class="place-combo-input"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            spellcheck="false"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $listId }}"
        >
    </label>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-place-value>
    <ul class="place-combo-list" id="{{ $listId }}" role="listbox" hidden></ul>
</div>

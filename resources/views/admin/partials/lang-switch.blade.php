<div class="lang-switch" role="tablist" aria-label="{{ __('admin.lang_switch') }}">
    <span class="lang-switch-label">{{ __('admin.lang_switch') }}</span>
    <button type="button" role="tab" data-lang-pick="hu" aria-selected="{{ app()->getLocale() === 'en' ? 'false' : 'true' }}" @class(['is-active' => app()->getLocale() !== 'en'])>Magyar</button>
    <button type="button" role="tab" data-lang-pick="en" aria-selected="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}" @class(['is-active' => app()->getLocale() === 'en'])>English</button>
</div>

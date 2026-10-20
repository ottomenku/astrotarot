@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    @php
        $selectedPerks = old('feature_keys', $package->perk_keys ?? []);
        $selectedPerks = is_array($selectedPerks) ? array_values($selectedPerks) : [];
        $perkLabels = $perkLabels ?? [];
        foreach ($perkKeys as $key) {
            $perkLabels[$key] = [
                'hu' => $perkLabels[$key]['hu'] ?? \App\Support\PackageBody::perkLabel($key, 'hu'),
                'en' => $perkLabels[$key]['en'] ?? \App\Support\PackageBody::perkLabel($key, 'en'),
            ];
        }
        $selectedIcon = old('icon', $package->icon ?? '');
    @endphp

    <main class="card">
        <h1>{{ $package->exists ? __('admin.packages.edit') : __('admin.packages.create') }}</h1>
        <p class="muted">{{ __('admin.packages.form_hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" data-package-form data-lang-scope data-active-lang="{{ app()->getLocale() === 'en' ? 'en' : 'hu' }}">
            @csrf
            @if ($package->exists)
                @method('PUT')
            @endif
            @include('admin.partials.lang-switch')

            <div class="row">
                <div class="field" data-lang="hu">
                    <label for="name_hu">{{ __('admin.packages.name_hu') }}</label>
                    <input id="name_hu" name="name_hu" value="{{ old('name_hu', $package->name_hu) }}" required>
                    @error('name_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field" data-lang="en">
                    <label for="name_en">{{ __('admin.packages.name_en') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $package->name_en) }}" required>
                    @error('name_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="slug">{{ __('admin.packages.slug') }}</label>
                    <input id="slug" name="slug" value="{{ old('slug', $package->slug) }}" placeholder="premium">
                    <p class="muted">{{ __('admin.packages.slug_hint') }}</p>
                    @error('slug') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="icon">{{ __('admin.packages.icon') }}</label>
                    <select id="icon" name="icon">
                        <option value="" @selected($selectedIcon === '')>{{ __('admin.packages.icon_none') }}</option>
                        <option value="heart" @selected($selectedIcon === 'heart')>{{ __('admin.packages.icon_heart') }}</option>
                        <option value="sun" @selected($selectedIcon === 'sun')>{{ __('admin.packages.icon_sun') }}</option>
                        <option value="crown" @selected($selectedIcon === 'crown')>{{ __('admin.packages.icon_crown') }}</option>
                        <option value="star" @selected($selectedIcon === 'star')>{{ __('admin.packages.icon_star') }}</option>
                    </select>
                    <p class="muted">{{ __('admin.packages.icon_hint') }}</p>
                </div>
                <div class="field">
                    <label for="sort_order">{{ __('admin.packages.sort_order') }}</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $package->sort_order ?? 0) }}" required>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="price_huf">{{ __('admin.packages.price_huf') }}</label>
                    <input id="price_huf" name="price_huf" type="number" min="0" value="{{ old('price_huf', $package->price_huf ?? 0) }}" required>
                    <p class="muted">{{ __('admin.packages.price_huf_hint') }}</p>
                    @error('price_huf') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="price_usd">{{ __('admin.packages.price_usd') }}</label>
                    <input id="price_usd" name="price_usd" type="number" min="0" step="0.01" value="{{ old('price_usd', $package->price_usd ?? 0) }}" required>
                    <p class="muted">{{ __('admin.packages.price_usd_hint') }}</p>
                    @error('price_usd') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="period">{{ __('admin.packages.period') }}</label>
                    <select id="period" name="period" required data-perk-source>
                        <option value="monthly" @selected(old('period', $package->period) === 'monthly')>{{ __('admin.packages.period_monthly') }}</option>
                        <option value="yearly" @selected(old('period', $package->period) === 'yearly')>{{ __('admin.packages.period_yearly') }}</option>
                    </select>
                </div>
                <div class="field">
                    <label for="credits">{{ __('admin.packages.credits') }}</label>
                    <input id="credits" name="credits" type="number" min="0" value="{{ old('credits', $package->credits ?? 0) }}" required data-perk-source>
                    <p class="muted">{{ __('admin.packages.credits_hint') }}</p>
                </div>
            </div>

            <fieldset class="field" style="border:1px solid var(--line);border-radius:.4rem;padding:.85rem 1rem">
                <legend style="padding:0 .4rem">{{ __('admin.packages.perks') }}</legend>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem 1.1rem">
                    @foreach ($perkKeys as $key)
                        <label style="display:flex;align-items:center;gap:.4rem;margin:0;color:inherit">
                            <input
                                type="checkbox"
                                name="feature_keys[]"
                                value="{{ $key }}"
                                style="width:auto"
                                data-perk-key="{{ $key }}"
                                @checked(in_array($key, $selectedPerks, true))
                            >
                            {{ $perkLabels[$key][app()->getLocale() === 'en' ? 'en' : 'hu'] }}
                        </label>
                    @endforeach
                </div>
                <p class="muted" style="margin:.85rem 0 0">{{ __('admin.packages.perks_how') }}</p>
            </fieldset>

            <div class="field" data-lang="hu">
                <label for="body_hu">{{ __('admin.packages.body_hu') }}</label>
                <textarea id="body_hu" name="body_hu" rows="10" class="js-html-editor" data-editor-locale="hu">{{ old('body_hu', $package->body_hu) }}</textarea>
                <p class="muted">{{ __('admin.packages.body_hint') }}</p>
                @error('body_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="body_en">{{ __('admin.packages.body_en') }}</label>
                <textarea id="body_en" name="body_en" rows="10" class="js-html-editor" data-editor-locale="en">{{ old('body_en', $package->body_en) }}</textarea>
                @error('body_en') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <label class="field" style="display:block">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" style="width:auto" @checked(old('is_active', $package->is_active ?? true))>
                {{ __('admin.packages.active') }}
            </label>

            <p>
                <button type="submit">{{ __('admin.packages.save') }}</button>
                <a href="{{ route('admin.packages.index') }}">{{ __('admin.packages.back') }}</a>
            </p>
        </form>
    </main>
@endsection

@push('scripts')
<script>
(() => {
  const form = document.querySelector('[data-package-form]');
  if (!form) return;

  const perkLabels = @json($perkLabels);
  const editors = {};

  function bindEditor(textarea) {
    const locale = textarea.dataset.editorLocale || 'hu';
    const wrap = document.createElement('div');
    wrap.className = 'html-editor';
    const toolbar = document.createElement('div');
    toolbar.className = 'html-editor-toolbar';
    const commands = [
      ['bold', 'B'],
      ['italic', 'I'],
      ['insertUnorderedList', '• lista'],
      ['insertOrderedList', '1. lista'],
      ['formatBlock:h3', 'Címsor'],
      ['formatBlock:p', 'Bekezdés'],
    ];
    commands.forEach(([command, label]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.addEventListener('click', (event) => {
        event.preventDefault();
        surface.focus();
        const [name, value] = command.split(':');
        document.execCommand(name, false, value || null);
        sync();
      });
      toolbar.appendChild(button);
    });
    const linkBtn = document.createElement('button');
    linkBtn.type = 'button';
    linkBtn.textContent = 'Link';
    linkBtn.addEventListener('click', (event) => {
      event.preventDefault();
      const href = window.prompt('URL', 'https://');
      if (!href) return;
      surface.focus();
      document.execCommand('createLink', false, href);
      sync();
    });
    toolbar.appendChild(linkBtn);

    const surface = document.createElement('div');
    surface.className = 'html-editor-surface';
    surface.contentEditable = 'true';
    surface.innerHTML = textarea.value || '';
    textarea.classList.add('html-editor-source');
    textarea.parentNode.insertBefore(wrap, textarea);
    wrap.appendChild(toolbar);
    wrap.appendChild(surface);
    wrap.appendChild(textarea);

    function sync() {
      textarea.value = surface.innerHTML;
    }
    surface.addEventListener('input', sync);
    editors[locale] = { textarea, surface, sync };
  }

  function stripPerks(html) {
    return html.replace(/<ul[^>]*class="[^"]*package-perks[^"]*"[^>]*>[\s\S]*?<\/ul>/gi, '').trim();
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function creditsLine(locale) {
    const credits = Number.parseInt(form.querySelector('[name="credits"]')?.value || '0', 10) || 0;
    if (credits < 1) return '';
    const yearly = form.querySelector('[name="period"]')?.value === 'yearly';
    if (locale === 'en') {
      return yearly ? `${credits} credits / year` : `${credits} credits / month`;
    }
    return yearly ? `${credits} kredit / év` : `${credits} kredit / hó`;
  }

  function selectedKeys() {
    return [...form.querySelectorAll('[data-perk-key]:checked')].map((node) => node.value);
  }

  function perksHtml(locale) {
    const items = [];
    const credits = creditsLine(locale);
    if (credits) items.push(credits);
    selectedKeys().forEach((key) => {
      const label = perkLabels[key]?.[locale];
      if (label) items.push(label);
    });
    if (!items.length) return '';
    return `<ul class="package-perks">${items.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  function applyPerks() {
    ['hu', 'en'].forEach((locale) => {
      const editor = editors[locale];
      if (!editor) return;
      const next = `${stripPerks(editor.surface.innerHTML)}${perksHtml(locale)}`;
      editor.surface.innerHTML = next;
      editor.sync();
    });
  }

  form.querySelectorAll('.js-html-editor').forEach(bindEditor);
  applyPerks();
  form.querySelectorAll('[data-perk-key], [data-perk-source]').forEach((node) => {
    node.addEventListener('change', applyPerks);
    node.addEventListener('input', applyPerks);
  });
  form.addEventListener('submit', () => {
    Object.values(editors).forEach((editor) => editor.sync());
  });
})();
</script>
@endpush

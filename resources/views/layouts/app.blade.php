<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Tarot') }}</title>
    <style>
        :root { color-scheme: light; --bg: #f7f4ef; --ink: #1f1a17; --muted: #5c534c; --line: #d8d0c6; --accent: #8a4b2f; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: linear-gradient(160deg, #f7f4ef, #ebe4da); color: var(--ink); min-height: 100vh; }
        a { color: var(--accent); }
        .wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .brand { font-size: 1.4rem; letter-spacing: .04em; text-decoration: none; color: inherit; font-weight: 700; }
        .nav { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        .btn, button, input[type=submit] { appearance: none; border: 1px solid var(--line); background: #fff; color: var(--ink); padding: .45rem .8rem; border-radius: .35rem; cursor: pointer; font: inherit; }
        .btn-primary, button[type=submit], input[type=submit] { background: var(--accent); border-color: var(--accent); color: #fff; }
        .card { background: rgba(255,255,255,.72); border: 1px solid var(--line); border-radius: .5rem; padding: 1.25rem; }
        label { display: block; margin-bottom: .25rem; color: var(--muted); font-size: .95rem; }
        input, select, textarea { width: 100%; padding: .5rem .65rem; border: 1px solid var(--line); border-radius: .35rem; font: inherit; background: #fff; }
        input[type=checkbox], input[type=radio] { width: 1.05rem; height: 1.05rem; padding: 0; margin: .15rem 0 0; flex: none; accent-color: var(--accent); }
        label:has(> input[type=checkbox]), label:has(> input[type=radio]) { display: flex; align-items: flex-start; gap: .45rem; }
        .field { margin-bottom: 1rem; }
        .row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid var(--line); padding: .55rem .4rem; text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; }
        .flash { background: #e8f5e9; border: 1px solid #a5d6a7; padding: .75rem 1rem; border-radius: .35rem; margin-bottom: 1rem; }
        .error { background: #fdecea; border: 1px solid #f5c2c0; padding: .75rem 1rem; border-radius: .35rem; margin-bottom: 1rem; color: #8a1f11; }
        .muted { color: var(--muted); }
        .admin-nav { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .admin-nav a { text-decoration: none; }
        .admin-nav a.active { font-weight: 700; text-decoration: underline; }
        .rule-card { position: relative; border: 1px solid var(--line); border-radius: .4rem; padding: .85rem 3.2rem .35rem .9rem; margin-bottom: .85rem; background: rgba(255,255,255,.45); }
        .rule-remove { position: absolute; top: .55rem; right: .55rem; }
        .pack-card-layout { display: flex; gap: 1rem; align-items: flex-start; }
        .pack-card-preview { width: 7.5rem; flex: none; }
        .pack-card-preview img { width: 88px; height: 132px; object-fit: cover; border-radius: .35rem; background: #efe8de; display: block; margin-bottom: .4rem; }
        .html-editor-toolbar { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .4rem; }
        .html-editor-toolbar button { width: auto; background: #fff; color: var(--ink); }
        .html-editor-surface { min-height: 11rem; border: 1px solid var(--line); border-radius: .35rem; padding: .65rem .8rem; background: #fff; }
        .html-editor-surface:focus { outline: 2px solid color-mix(in srgb, var(--accent) 35%, white); }
        .html-editor-source { position: absolute; left: -9999px; width: 1px; height: 1px; }
        .html-editor { position: relative; }
        .feature-marks { display:inline-flex; align-items:center; gap:3px; vertical-align:middle; }
        .feature-mark { display:inline-flex; }
        .pkg-icon { width:12px; height:12px; display:block; flex:none; }
        .pkg-icon-heart { color:#E8C547; }
        .pkg-icon-sun { color:#F0A020; }
        .pkg-icon-crown { color:#D4AF37; }
        .pkg-icon-star { color:#E8C547; }
        .admin-icon { width:18px; height:18px; display:block; }
        .admin-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:2rem; height:2rem; padding:0; border:1px solid var(--line); background:#fff; color:var(--ink); border-radius:.35rem; text-decoration:none; }
        .admin-icon-btn.is-blue { color:#1565c0; border-color:#90caf9; background:#e3f2fd; }
        .admin-icon-btn.is-red { color:#c62828; border-color:#ef9a9a; background:#ffebee; }
        .admin-icon-btn.is-danger { color:#c62828; }
        .admin-icon-btn.is-on { color:#2e7d32; border-color:#a5d6a7; background:#e8f5e9; }
        .admin-actions { display:flex; gap:.35rem; align-items:center; white-space:nowrap; }
        .admin-actions form { display:inline; margin:0; }
        .admin-col-icon { width:2.2rem; padding-left:.2rem; padding-right:.2rem; }
        .admin-role-icon { display:inline-flex; color:var(--muted); }
        .admin-role-icon .pkg-icon, .admin-role-icon .admin-icon { width:16px; height:16px; }
        .admin-credit-form { display:flex; align-items:center; gap:.35rem; margin:0; }
        .admin-credit-form input { width:4.2rem; padding:.25rem .4rem; }
        .admin-credit-form .admin-icon-btn { width:1.85rem; height:1.85rem; }
        table.admin-compact th, table.admin-compact td { padding:.45rem .35rem; }
        tr.is-banned td { color:#8a1f11; background:rgba(245,194,192,.28); }
        .lang-switch { display:flex; align-items:flex-end; gap:.4rem; margin:0 0 1rem; padding:.7rem 0 0; background:#fbf8f4; border-bottom:2px solid var(--accent); }
        form > .lang-switch:first-of-type { position:sticky; top:0; z-index:6; }
        .lang-switch-label { margin:0 .25rem .55rem 0; color:var(--muted); font-size:.95rem; }
        .lang-switch button { width:auto; min-width:8.5rem; margin:0; padding:.65rem 1.2rem; border:1px solid var(--line); border-bottom:none; border-radius:.5rem .5rem 0 0; background:#efe8de; color:var(--ink); font-weight:700; }
        .lang-switch button.is-active { background:#fff; color:var(--accent); border-color:var(--accent); box-shadow:inset 0 -3px 0 var(--accent); }
        .lang-switch button.has-error:not(.is-active) { box-shadow: inset 0 -3px 0 #b33; }
        [data-lang-scope][data-active-lang="hu"] [data-lang="en"],
        [data-lang-scope][data-active-lang="en"] [data-lang="hu"] { display:none !important; }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="top">
            <a class="brand" href="{{ route('home') }}">{{ config('app.name', 'Tarot') }}</a>
            <div class="nav">
                <span class="muted">{{ __('messages.language') }}:</span>
                <a href="{{ route('locale.switch', 'hu') }}">HU</a>
                <a href="{{ route('locale.switch', 'en') }}">EN</a>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.ephemeris.index') }}">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button type="submit">{{ __('messages.logout') }}</button>
                    </form>
                @else
                    <a class="btn" href="{{ route('login') }}">{{ __('messages.login') }}</a>
                @endauth
            </div>
        </header>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
    <script>
        (() => {
            const activateLang = (scope, lang) => {
                if (!scope || (lang !== 'hu' && lang !== 'en')) return;
                scope.dataset.activeLang = lang;
                scope.querySelectorAll('[data-lang-pick]').forEach((button) => {
                    const on = button.dataset.langPick === lang;
                    button.classList.toggle('is-active', on);
                    button.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            };
            const langOf = (field) => field.closest?.('[data-lang]')?.dataset.lang || '';
            const revealInvalid = (form) => {
                const field = [...form.querySelectorAll('input, textarea, select')].find((el) => {
                    if (!el.willValidate || el.disabled || el.type === 'hidden') return false;
                    return !el.checkValidity() && langOf(el);
                });
                if (!field) return;
                activateLang(field.closest('[data-lang-scope]') || form, langOf(field));
            };
            document.addEventListener('click', (event) => {
                const pick = event.target.closest?.('[data-lang-pick]');
                if (pick) {
                    event.preventDefault();
                    activateLang(pick.closest('[data-lang-scope]'), pick.dataset.langPick);
                    return;
                }
                const button = event.target.closest?.('button[type="submit"], input[type="submit"]');
                if (button?.form?.matches('[data-lang-scope]')) revealInvalid(button.form);
            }, true);
            document.addEventListener('invalid', (event) => {
                const field = event.target;
                const scope = field.closest?.('[data-lang-scope]');
                if (!scope) return;
                activateLang(scope, langOf(field));
            }, true);
            document.querySelectorAll('[data-lang-scope]').forEach((scope) => {
                const hasError = (lang) => !!scope.querySelector(`[data-lang="${lang}"] p[style*="#b33"]`);
                ['hu', 'en'].forEach((lang) => {
                    scope.querySelector(`[data-lang-pick="${lang}"]`)?.classList.toggle('has-error', hasError(lang));
                });
                const current = scope.dataset.activeLang === 'en' ? 'en' : 'hu';
                const other = current === 'hu' ? 'en' : 'hu';
                activateLang(scope, !hasError(current) && hasError(other) ? other : current);
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>

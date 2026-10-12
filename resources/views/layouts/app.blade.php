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
        .rule-card { border: 1px solid var(--line); border-radius: .4rem; padding: .85rem .9rem .35rem; margin-bottom: .85rem; background: rgba(255,255,255,.45); }
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
    @stack('scripts')
</body>
</html>

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
</body>
</html>

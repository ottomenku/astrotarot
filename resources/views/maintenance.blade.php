<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.maintenance_title') }} · {{ config('app.name', 'AstroTarot') }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #eee8da;
            background:
                radial-gradient(circle at 18% 12%, rgba(90,55,130,.28), transparent 24rem),
                linear-gradient(180deg, #0a0b16 0%, #121225 45%, #080912 100%);
            font-family: Georgia, "Times New Roman", serif;
            text-align: center;
        }
        main { max-width: 28rem; }
        .mark {
            width: 52px; height: 52px; margin: 0 auto 18px;
            display: grid; place-items: center;
            border: 1px solid #d9b86e; transform: rotate(45deg); background: #0c0d1b;
            color: #f2ddb0;
        }
        .mark span { transform: rotate(-45deg); font-size: 20px; }
        h1 { margin: 0 0 12px; font: italic 2rem/1.2 Georgia, serif; color: #f2ddb0; }
        p { margin: 0; color: #b9b4c0; font-size: 1.05rem; line-height: 1.55; }
        a { display: inline-block; margin-top: 28px; color: #d9b86e; }
    </style>
</head>
<body>
<main>
    <div class="mark" aria-hidden="true"><span>✦</span></div>
    <h1>{{ __('messages.maintenance_title') }}</h1>
    <p>{{ __('messages.maintenance_body') }}</p>
    <a href="{{ route('login') }}">{{ __('messages.login') }}</a>
</main>
</body>
</html>

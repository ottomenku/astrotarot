<!DOCTYPE html>
<html lang="{{ $payload['locale'] ?? 'hu' }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1b1724; font-size: 12px; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0 0 6px; color: #5b3d12; }
        .kicker { color: #8a6a2a; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; margin: 0 0 18px; }
        .meta { margin: 0 0 18px; color: #555; font-size: 11px; }
        .block { margin: 0 0 18px; padding-bottom: 14px; border-bottom: 1px solid #d9c49a; }
        .block h2 { font-size: 14px; margin: 0 0 8px; color: #5b3d12; }
        .question { font-size: 14px; font-style: italic; }
        .cards { width: 100%; border-collapse: collapse; }
        .cards td { width: 50%; vertical-align: top; padding: 0 10px 0 0; }
        .cards td:last-child { padding: 0 0 0 10px; }
        .card-title { font-weight: bold; margin: 0 0 4px; }
        .card-sub { color: #666; font-size: 11px; margin: 0 0 6px; }
        .card-body { white-space: pre-wrap; }
        .answer { white-space: pre-wrap; margin: 0; }
        .answer-label { font-weight: bold; color: #5b3d12; margin: 0 0 6px; }
        img.card-img { max-width: 120px; max-height: 180px; display: block; margin: 0 0 8px; }
    </style>
</head>
<body>
    <h1>AstroTarot</h1>
    <p class="kicker">{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Personal reading' : 'Személyes olvasat' }}</p>
    <p class="meta">{{ $generatedAt->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>

    <div class="block">
        <h2>{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Question' : 'Kérdés' }}</h2>
        <p class="question">{{ $payload['question'] }}</p>
    </div>

    <div class="block">
        <h2>{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Chosen cards' : 'Választott lapok' }}</h2>
        <table class="cards">
            <tr>
                @foreach ($payload['cards'] as $card)
                    <td>
                        <p class="card-title">{{ $card['slot_title'] }}</p>
                        <p class="card-sub">
                            {{ $card['figure_name'] }}
                            @if (!empty($card['polarity']))
                                · {{ $card['polarity'] }}
                            @endif
                        </p>
                        @if (!empty($card['keyword']))
                            <p class="card-sub">{{ $card['keyword'] }}</p>
                        @endif
                        @if (!empty($card['astro_caption']))
                            <p class="card-body">{{ $card['astro_caption'] }}</p>
                        @elseif (!empty($card['blurb']))
                            <p class="card-body">{{ $card['blurb'] }}</p>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    @foreach ($payload['answers'] as $answer)
        <div class="block">
            <p class="answer-label">{{ $answer['label'] }}</p>
            <p class="answer">{{ $answer['text'] }}</p>
        </div>
    @endforeach
</body>
</html>

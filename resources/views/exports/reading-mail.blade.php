<div style="font-family:Georgia,serif;color:#1b1724;line-height:1.5;max-width:640px">
    <p style="margin:0 0 8px;color:#8a6a2a;font-size:12px;letter-spacing:1px;text-transform:uppercase">AstroTarot</p>
    <h1 style="margin:0 0 16px;font-size:22px">
        {{ ($payload['locale'] ?? 'hu') === 'en' ? 'Your reading' : 'Az olvasatod' }}
    </h1>
    <p style="margin:0 0 18px">
        {{ ($payload['locale'] ?? 'hu') === 'en'
            ? 'Attached you will find the full reading as a PDF (question, cards, answers).'
            : 'Csatolmányban megkapod a teljes olvasatot PDF-ben (kérdés, lapok, válaszok).' }}
    </p>

    <h2 style="font-size:15px;margin:0 0 8px">{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Question' : 'Kérdés' }}</h2>
    <p style="font-style:italic;margin:0 0 18px">{{ $payload['question'] }}</p>

    <h2 style="font-size:15px;margin:0 0 8px">{{ ($payload['locale'] ?? 'hu') === 'en' ? 'Chosen cards' : 'Választott lapok' }}</h2>
    @foreach ($payload['cards'] as $card)
        <p style="margin:0 0 10px">
            <strong>{{ $card['slot_title'] }}:</strong>
            {{ $card['figure_name'] }}
            @if (!empty($card['astro_caption']))
                — {{ $card['astro_caption'] }}
            @elseif (!empty($card['blurb']))
                — {{ $card['blurb'] }}
            @endif
        </p>
    @endforeach

    @foreach ($payload['answers'] as $answer)
        <h2 style="font-size:15px;margin:18px 0 8px">{{ $answer['label'] }}</h2>
        <p style="white-space:pre-wrap;margin:0">{{ $answer['text'] }}</p>
    @endforeach
</div>

<?php

namespace App\Http\Controllers;

use App\Mail\ReadingExportMail;
use App\Models\ReadingShare;
use App\Services\ReadingExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class ReadingExportController extends Controller
{
    public function __construct(private readonly ReadingExportService $exports)
    {
    }

    public function pdf(Request $request): Response
    {
        $payload = $this->exports->normalizePayload($request->all());
        $pdf = $this->exports->makePdf($payload);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="astrotarot-olvasat.pdf"',
        ]);
    }

    public function email(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'question' => ['required', 'string'],
            'locale' => ['nullable', 'string', 'in:hu,en'],
            'cards' => ['required', 'array'],
            'answers' => ['required', 'array', 'min:1'],
        ]);

        $payload = $this->exports->normalizePayload($data);
        $to = $data['email'] ?? $request->user()?->email;

        if (! filled($to)) {
            return response()->json([
                'error' => ($payload['locale'] === 'en')
                    ? 'An email address is required.'
                    : 'E-mail cím szükséges.',
            ], 422);
        }

        try {
            $pdf = $this->exports->makePdf($payload);
            Mail::to($to)->send(new ReadingExportMail($payload, $pdf));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => ($payload['locale'] === 'en')
                    ? 'The email could not be sent right now.'
                    : 'Az e-mail most nem küldhető el.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'email' => $to,
            'message' => ($payload['locale'] === 'en')
                ? 'The reading was sent by email.'
                : 'Az olvasatot elküldtük e-mailben.',
        ]);
    }

    public function share(Request $request): JsonResponse
    {
        $payload = $this->exports->normalizePayload($request->all());
        $share = $this->exports->createShare($request->user(), $payload);
        $url = route('reading.share.show', $share->token);

        return response()->json([
            'ok' => true,
            'url' => $url,
            'facebook_url' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url),
        ]);
    }

    public function show(string $token): View
    {
        $share = ReadingShare::query()->where('token', $token)->firstOrFail();
        abort_if($share->isExpired(), 410);

        $payload = $share->payload;
        $locale = ($payload['locale'] ?? 'hu') === 'en' ? 'en' : 'hu';
        $firstAnswer = $payload['answers'][0]['text'] ?? '';
        $description = mb_substr(preg_replace('/\s+/u', ' ', (string) $firstAnswer) ?? '', 0, 180);

        return view('exports.share', [
            'payload' => $payload,
            'shareUrl' => route('reading.share.show', $share->token),
            'ogTitle' => $locale === 'en' ? 'AstroTarot reading' : 'AstroTarot olvasat',
            'ogDescription' => $description !== '' ? $description : ($payload['question'] ?? 'AstroTarot'),
            'assetBase' => asset('templates/art-deco'),
        ]);
    }
}

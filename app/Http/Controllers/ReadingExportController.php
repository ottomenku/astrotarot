<?php

namespace App\Http\Controllers;

use App\Mail\ReadingExportMail;
use App\Models\ReadingShare;
use App\Services\CreditService;
use App\Services\OgImageService;
use App\Services\ReadingExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class ReadingExportController extends Controller
{
    public function __construct(
        private readonly ReadingExportService $exports,
        private readonly CreditService $credits,
    ) {
    }

    public function pdf(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $this->credits->canExportPdf($user)) {
            return response()->json([
                'error' => __('messages.plan_pdf_locked'),
            ], 403);
        }

        $payload = $this->exports->normalizePayload($request->all());
        $pdf = $this->exports->makePdf($payload);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="astrotarot-olvasat.pdf"',
        ]);
    }

    public function email(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $this->credits->canExportPdf($user)) {
            return response()->json([
                'error' => __('messages.plan_pdf_locked'),
            ], 403);
        }
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

    public function show(string $token, OgImageService $ogImages): View
    {
        $share = ReadingShare::query()->where('token', $token)->firstOrFail();
        abort_if($share->isExpired(), 410);

        $payload = $share->payload;
        $locale = ($payload['locale'] ?? 'hu') === 'en' ? 'en' : 'hu';
        $question = trim((string) ($payload['question'] ?? ''));
        $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $answerTexts = [];
        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            $text = trim((string) ($answer['text'] ?? ''));
            if ($text !== '') {
                $answerTexts[] = $text;
            }
        }
        $combined = trim($question.($answerTexts !== [] ? ' — '.implode(' ', $answerTexts) : ''));
        $description = mb_substr(preg_replace('/\s+/u', ' ', $combined) ?? '', 0, 220);

        $ogImage = route('reading.share.og', $share->token);
        if (! $ogImages->canGenerate()) {
            $firstCard = $payload['cards'][0] ?? null;
            $ogImage = is_array($firstCard) && ! empty($firstCard['image_url'])
                ? (string) $firstCard['image_url']
                : asset('og/astrotarot-promo.jpg');
        }

        return view('exports.share', [
            'payload' => $payload,
            'shareUrl' => route('reading.share.show', $share->token),
            'ogTitle' => 'AstroTarot',
            'ogDescription' => $description !== '' ? $description : 'AstroTarot',
            'ogImage' => $ogImage,
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function ogImage(string $token, OgImageService $ogImages): Response
    {
        $share = ReadingShare::query()->where('token', $token)->firstOrFail();
        abort_if($share->isExpired(), 410);

        $cards = is_array($share->payload['cards'] ?? null) ? $share->payload['cards'] : [];
        $jpeg = $ogImages->makeTwoCardJpeg($cards);
        if ($jpeg === null) {
            $fallback = public_path('og/astrotarot-promo.jpg');
            abort_unless(is_file($fallback), 404);

            return response()->file($fallback, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

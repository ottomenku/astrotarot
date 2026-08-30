<?php

namespace App\Http\Controllers;

use App\Mail\ReadingExportMail;
use App\Models\ReadingShare;
use App\Models\User;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\OgImageService;
use App\Services\ReadingExportService;
use App\Support\FeatureCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ReadingExportController extends Controller
{
    public function __construct(
        private readonly ReadingExportService $exports,
        private readonly CreditService $credits,
        private readonly FeatureAccessService $features,
    ) {}

    public function pdf(Request $request): Response|JsonResponse
    {
        $denied = $this->denyJson($request, FeatureCatalog::PDF);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $balance = $this->charge($request->user(), FeatureCatalog::PDF);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'credits' => (int) $request->user()->fresh()->credits,
            ], 402);
        }

        $payload = $this->exports->normalizePayload($request->all());
        $pdf = $this->exports->makePdf($payload);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="astrotarot-olvasat.pdf"',
            'X-Credits-Remaining' => (string) $balance,
        ]);
    }

    public function email(Request $request): JsonResponse
    {
        $denied = $this->denyJson($request, FeatureCatalog::EMAIL);
        if ($denied !== null) {
            return $denied;
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
            $balance = $this->charge($request->user(), FeatureCatalog::EMAIL);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'credits' => (int) $request->user()->fresh()->credits,
            ], 402);
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
            'credits' => $balance,
            'message' => ($payload['locale'] === 'en')
                ? 'The reading was sent by email.'
                : 'Az olvasatot elküldtük e-mailben.',
        ]);
    }

    public function share(Request $request): JsonResponse
    {
        $denied = $this->denyJson($request, FeatureCatalog::SHARE);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $balance = $this->charge($request->user(), FeatureCatalog::SHARE);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'credits' => (int) $request->user()->fresh()->credits,
            ], 402);
        }

        $payload = $this->exports->normalizePayload($request->all());
        $share = $this->exports->createShare($request->user(), $payload);
        $url = route('reading.share.show', $share->token);

        return response()->json([
            'ok' => true,
            'url' => $url,
            'credits' => $balance,
            'facebook_url' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url),
        ]);
    }

    public function show(string $token): View
    {
        $share = ReadingShare::query()->where('token', $token)->firstOrFail();
        abort_if($share->isExpired(), 410);

        $payload = $share->payload;
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

        // Always point Facebook/OG at the composite endpoint (never a raw card PNG).
        $ogImage = route('reading.share.og', $share->token);

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

    private function denyJson(Request $request, string $key): ?JsonResponse
    {
        $user = $request->user();
        $decision = $this->features->decide($user, $key);
        if ($decision['allowed']) {
            return null;
        }

        return response()->json([
            'error' => $decision['message'],
            'credits' => (int) ($user?->credits ?? 0),
        ], $decision['status']);
    }

    private function charge(?User $user, string $key): int
    {
        if ($user === null) {
            return 0;
        }

        $decision = $this->features->decide($user->fresh(), $key);
        if ($decision['will_charge']) {
            return $this->credits->spend($user, $decision['cost']);
        }

        return (int) $user->fresh()->credits;
    }
}

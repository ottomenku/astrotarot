<?php

namespace App\Http\Middleware;

use App\Services\LlmTranscript;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetLlmTranscript
{
    public function __construct(private readonly LlmTranscript $transcript) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->transcript->reset();

        return $next($request);
    }
}

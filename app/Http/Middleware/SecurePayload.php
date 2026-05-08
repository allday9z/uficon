<?php

namespace App\Http\Middleware;

use App\Services\JoseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurePayload
{
    public function handle(Request $request, Closure $next): Response
    {
        $compactJwe = $request->input('data');
        if (!$compactJwe || !is_string($compactJwe)) {
            return response()->json(['message' => 'Bad Request: Missing encrypted payload'], 400);
        }

        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }
        $jweKey = $appKey;
        $jwsKey = $appKey;

        try {
            $nestedJws = JoseService::decryptJwe($compactJwe, $jweKey);
            $payload = JoseService::verifyJws($nestedJws, $jwsKey);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Bad Request: Invalid JWS/JWE', 'error' => $e->getMessage()], 400);
        }

        $request->replace($payload);

        return $next($request);
    }
}

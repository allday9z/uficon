<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiBearerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized: Missing Bearer Token'], 401);
        }

        $apiToken = ApiToken::where('token', $token)->first();

        if (!$apiToken || !$apiToken->is_active) {
            return response()->json(['message' => 'Unauthorized: Invalid or Inactive Token'], 401);
        }

        $request->attributes->set('api_token_id', $apiToken->id);

        return $next($request);
    }
}

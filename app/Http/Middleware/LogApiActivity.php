<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $apiToken = null;
        $bearer = $request->bearerToken();
        if ($bearer) {
            $apiToken = ApiToken::where('token', $bearer)->first();
        } else {
            $headerToken = $request->header('X-Project-Token');
            if ($headerToken) {
                $apiToken = ApiToken::where('token', $headerToken)->first();
            }
        }

        $response = $next($request);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Log the activity
        ApiLog::create([
            'api_token_id' => $apiToken ? $apiToken->id : null,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payload' => $request->all(), // Capture request payload
            'response' => $this->formatResponse($response),
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'duration_ms' => round($duration, 2),
        ]);

        return $response;
    }

    protected function formatResponse(Response $response)
    {
        $content = $response->getContent();
        
        // Try to decode JSON response
        $decoded = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return ['content' => substr($content, 0, 1000)]; // Truncate long non-JSON responses
    }
}

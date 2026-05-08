<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckProjectToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = env('MY_PROJECT_TOKEN');

        if (!$expectedToken) {
             return response()->json(['message' => 'Server configuration error: Token not set'], 500);
        }

        $providedToken = $request->header('X-Project-Token');

        if ($providedToken !== $expectedToken) {
            return response()->json(['message' => 'Unauthorized: Invalid Project Token'], 401);
        }

        return $next($request);
    }
}

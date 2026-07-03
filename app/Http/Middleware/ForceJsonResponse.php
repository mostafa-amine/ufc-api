<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Treat every API request as JSON so error responses (401/404/422/429) always
 * render as the JSON envelope, even when the client omits the Accept header.
 * Without this, an unauthenticated request with no Accept header falls through
 * to Laravel's redirect-to-`login` path and 500s (there is no login route).
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}

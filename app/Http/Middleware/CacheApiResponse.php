<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The data only changes once a day, so tell clients they may keep a good
 * answer for a few minutes, and give each answer a fingerprint (ETag). A client
 * that sends the fingerprint back gets an empty "not changed" reply instead of
 * the whole body again.
 *
 * Only successful GET answers are marked: errors and rate-limit replies must
 * never be reused.
 */
class CacheApiResponse
{
    public function handle(Request $request, Closure $next, int $maxAge = 300): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $response->setCache(['private' => true, 'max_age' => $maxAge]);
        // A saved answer only counts for the same key; a call with another key (or none) asks again.
        $response->setVary('Authorization', false);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}

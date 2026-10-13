<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API request is treated as a JSON request.
 *
 * Without this, an unauthenticated request lacking an `Accept: application/json`
 * header makes Laravel attempt a redirect to the (non-existent) `login` route and
 * return a 500. Forcing JSON means protected API routes always answer with a clean
 * 401/403 JSON body.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}

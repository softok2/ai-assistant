<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the club admin panels may embed this app in an iframe.
 */
final class FrameAncestors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ancestors = config('app.frame_ancestors');

        if (filled($ancestors)) {
            $response->headers->set(
                'Content-Security-Policy',
                "frame-ancestors 'self' {$ancestors}"
            );
        }

        return $response;
    }
}

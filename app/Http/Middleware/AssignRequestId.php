<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A simple request-correlation id: honors an inbound X-Request-Id (e.g. from
 * a load balancer or upstream proxy) if present, otherwise generates one.
 * Added to the log context (so every log line for this request carries it —
 * see config/logging.php's context) and echoed back as a response header,
 * so a reviewer/operator can correlate a browser request with server logs.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER) ?: (string) Str::uuid();

        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}

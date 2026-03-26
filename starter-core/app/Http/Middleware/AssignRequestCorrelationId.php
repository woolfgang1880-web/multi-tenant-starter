<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asigna request_id/trace_id por request y los propaga a logs + response headers.
 */
final class AssignRequestCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->header('X-Request-Id', ''));
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $traceId = trim((string) $request->header('X-Trace-Id', ''));
        if ($traceId === '') {
            $traceId = $requestId;
        }

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);

        app()->instance('request_id', $requestId);
        app()->instance('trace_id', $traceId);

        Log::shareContext([
            'request_id' => $requestId,
            'trace_id' => $traceId,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}


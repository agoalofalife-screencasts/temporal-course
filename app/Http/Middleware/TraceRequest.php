<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use Temporal\OpenTelemetry\Tracer;

class TraceRequest
{
    public function __construct(private readonly Tracer $tracer) {}

    public function handle(Request $request, Closure $next)
    {
        return $this->tracer->trace(
            name: $request->method().' '.$request->path(),
            // Running $next() INSIDE the callback keeps this server span active
            // for the whole request, so every DB::listen span and the Temporal
            // workflow-start span nest under it — one trace id end to end.
            callback: function () use ($request, $next) {
                $response = $next($request);

                // Expose the trace id so it can be correlated with logs / Zipkin.
                $traceId = Span::getCurrent()->getContext()->getTraceId();
                $response->headers->set('X-Trace-Id', $traceId);

                return $response;
            },
            attributes: [
                'http.request.method' => $request->method(),
                'url.path' => '/'.ltrim($request->path(), '/'),
            ],
            scoped: true,
            spanKind: SpanKind::KIND_SERVER,
        );
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Metrics\Metrics;
use Temporal\OpenTelemetry\Tracer;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Metrics::class, function ($app) {
            return new Metrics(RPC::create('tcp://127.0.0.1:6001')); // from config of course
        });

        // Shared OpenTelemetry tracer used by the Temporal interceptors.
        // Resolved through the container so keepsuit's `$app->make()` autowires
        // it into the OpenTelemetry* interceptors listed in config/temporal.php.
        $this->app->singleton(Tracer::class, function ($app) {
            $endpoint = rtrim(config('temporal.otel.endpoint'), '/').'/v1/traces';

            $transport = (new OtlpHttpTransportFactory())
                ->create($endpoint, ContentTypes::PROTOBUF);

            $resource = ResourceInfoFactory::defaultResource()->merge(
                ResourceInfo::create(Attributes::create([
                    'service.name' => config('temporal.otel.service_name'),
                ])),
            );

            $tracerProvider = new TracerProvider(
                // SimpleSpanProcessor exports each span synchronously. Under a
                // long-lived RoadRunner worker a batch processor might never
                // flush, so we trade throughput for guaranteed delivery.
                new SimpleSpanProcessor(new SpanExporter($transport)),
                resource: $resource,
            );

            return new Tracer(
                $tracerProvider->getTracer(config('temporal.otel.service_name')),
                TraceContextPropagator::getInstance(),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function ($query) {
            app(Tracer::class)->trace(
                name: 'db.query',
                callback: fn() => null,
                attributes: [
                    'db.system'        => $query->connection->getDriverName(),
                    'db.statement'     => $query->sql,
                    'db.duration_ms'   => $query->time,
                ],
                spanKind: SpanKind::KIND_CLIENT,
            );
        });

    }
}

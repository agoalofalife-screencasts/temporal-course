<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Temporal\Testing\Environment;

// The Temporal SDK download/extract path can exceed the default 128M limit.
ini_set('memory_limit', '512M');

/*
|--------------------------------------------------------------------------
| Temporal test mode — switch with one CLI env var
|--------------------------------------------------------------------------
|
|   php artisan test                            -> "dedicated" (default)
|       Real Temporal in the standalone `temporal-testing` docker container.
|       Only the RoadRunner worker is started here (server already running).
|
|   TEMPORAL_TEST_MODE=local php artisan test   -> in-process test server
|       Downloads + runs the Temporal time-skipping test server on 127.0.0.1:7233
|       (no docker container needed) AND the worker.
|
*/
$mode = getenv('TEMPORAL_TEST_MODE') ?: 'dedicated';
$address = $mode === 'local' ? '127.0.0.1:7233' : 'temporal-testing:7233';

// Override the container's baked-in TEMPORAL_ADDRESS (=temporal:7233), which
// would otherwise win over phpunit.xml. $_SERVER is what Laravel's env() reads.
putenv("TEMPORAL_ADDRESS={$address}");
$_ENV['TEMPORAL_ADDRESS'] = $_SERVER['TEMPORAL_ADDRESS'] = $address;

$environment = Environment::create();

if ($mode === 'local') {
    echo "Starting in-process Temporal test server + worker ({$address})...\n";
// start() boots the time-skipping test server AND the RoadRunner worker.
    $environment->start('/usr/local/bin/rr serve -c .rr.test.yaml');
} else {
    echo "Starting Temporal test worker (RoadRunner -> {$address})...\n";
// Dedicated server already running; only start the worker.
    $environment->startRoadRunner('/usr/local/bin/rr serve -c .rr.test.yaml');
}

register_shutdown_function(static function () use ($environment): void {
    echo "Stopping Temporal test stack...\n";
    $environment->stop();
});

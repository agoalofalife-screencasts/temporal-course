<?php

declare(strict_types=1);

namespace App\Temporal\Activities;

use Spiral\RoadRunner\Metrics\Metrics;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface(prefix: "metrics.")]
class MetricsActivity
{
    public function __construct(private Metrics $metrics) {} // DI работает

    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod]
    public function increment(string $name, float $value = 1): void
    {
        $this->metrics->add($name, $value);
    }
}

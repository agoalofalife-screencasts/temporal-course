<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** True when running against the in-process time-skipping test server. */
    protected function usesTimeSkippingServer(): bool
    {
        return (getenv('TEMPORAL_TEST_MODE') ?: 'dedicated') === 'local';
    }
}

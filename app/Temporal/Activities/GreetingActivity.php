<?php

namespace App\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'GreetingActivity')]
class GreetingActivity
{
    #[ActivityMethod(name: 'Greet')]
    public function greet(string $name): string
    {
        $timestamp = now()->format('Y-m-d H:i:s');

        return "Hello, {$name} Time now {$timestamp}";
    }
}

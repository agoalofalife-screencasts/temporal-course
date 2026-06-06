<?php

namespace App\Temporal\Activities;

use App\Models\Order;
use Ramsey\Uuid\UuidInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface]
class DatabaseActivity
{
    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod]
    public function updateOrderAddress(
        UuidInterface $orderId,
        string $newAddress,
    ): void
    {
        Order::findOrFail($orderId)->update(['address' => $newAddress]);
    }
}

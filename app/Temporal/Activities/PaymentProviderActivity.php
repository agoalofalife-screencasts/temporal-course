<?php

namespace App\Temporal\Activities;

use App\Models\Order;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface]
class PaymentProviderActivity
{
    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod]
    public function charge(
        Order $order,
        int $amountInCents,
    ): void
    {
        sleep(1);
        // charge
        // might be more complex logic with retry and idempotency key
    }
}

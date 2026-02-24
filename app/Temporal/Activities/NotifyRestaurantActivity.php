<?php

namespace App\Temporal\Activities;

use App\Modules\Order\Dto\OrderDto;
use Illuminate\Support\Facades\Log;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'NotifyRestaurant')]
class NotifyRestaurantActivity
{
    #[ActivityMethod(name: 'Notify')]
    public function notify(OrderDto $orderDto): void
    {
        $attempt = Activity::getInfo()->attempt;

        if ($attempt <= 2) {
            throw new \RuntimeException("Simulated failure on attempt {$attempt}");
        }

        sleep(10);

        Log::info('Notifying restaurant', [
            'order_id' => $orderDto->orderId(),
            'customer_name' => $orderDto->customerName(),
        ]);
    }
}

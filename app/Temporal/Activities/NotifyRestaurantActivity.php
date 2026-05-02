<?php

namespace App\Temporal\Activities;

use App\Modules\Order\Dto\OrderDto;
use Illuminate\Support\Facades\Log;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface(prefix: "NotifyRestaurant")]
class NotifyRestaurantActivity
{
    /** @return VirtualPromise<void> */
    #[ActivityMethod(name: "Notify")]
    public function notify(OrderDto $orderDto): void
    {
        $attempt = Activity::getInfo()->attempt;

        //        if ($attempt === 1) { // пример, тут конечно ответ от api ресторана
        //            // переопределяем retry options
        //            throw new \Temporal\Exception\Failure\ApplicationFailure(
        //                message: "Stocktaking is $attempt",
        //                type: 'my_failure_type',
        //                nonRetryable: false,
        //                nextRetryDelay: CarbonInterval::seconds(30),
        //            );
        //        }

        sleep(1);

        Log::info("Notifying restaurant after time is out", [
            "order_id" => $orderDto->orderId(),
            "customer_name" => $orderDto->customerName(),
        ]);
    }

    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod(name: "Cancel")]
    public function cancel(OrderDto $orderDto): void
    {
        sleep(1);
        Log::info("Cancelling order", [
            "order_id" => $orderDto->orderId(),
            "customer_name" => $orderDto->customerName(),
        ]);
    }
}

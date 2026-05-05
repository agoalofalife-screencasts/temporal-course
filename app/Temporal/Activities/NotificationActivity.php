<?php

declare(strict_types=1);

namespace App\Temporal\Activities;

use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface]
class NotificationActivity
{
    public function __construct(
//        private SmsGatewayInterface $smsGateway,
    ) {}

    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod]
    public function sendOrderConfirmationSms(
        string $phoneNumber,
        UuidInterface $orderId,
    ): void {
        $message = sprintf(
            "Отличные новости! Ресторан начал готовить ваш заказ #%s 🍕",
            $orderId->toString(),
        );

         sleep(1);
//        $this->smsGateway->send($phoneNumber, $message);

        Log::info("Sms sent to customer", [
            'orderId' => $orderId->toString(),
            'phone' => $phoneNumber,
        ]);
    }
}

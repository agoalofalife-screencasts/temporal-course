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
    public function sendRestaurantConfirmationSms(
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

    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod]
    public function sendRestaurantConfirmationPush(
        string $phoneNumber,
        UuidInterface $orderId,
    ): void {
        $message = sprintf(
            "Отличные новости! Ресторан начал готовить ваш заказ #%s 🍕",
            $orderId->toString(),
        );

        sleep(1);

        Log::info("Sms sent to customer", [
            'orderId' => $orderId->toString(),
            'phone' => $phoneNumber,
        ]);
    }
    /**
     * @return VirtualPromise<void>
     **/
    #[ActivityMethod]
    public function sendRestaurantConfirmationPushWithCode(
        string $phoneNumber,
        UuidInterface $orderId,
        string $code,
    ): void {
        $message = sprintf(
            "Отличные новости! Ресторан начал готовить ваш заказ #%s 🍕и tracing code: %s",
            $orderId->toString(),
            $code,
        );

        sleep(1);

        Log::info("Sms sent to customer", [
            'orderId' => $orderId->toString(),
            'phone' => $phoneNumber,
            'code' => $code,
        ]);
    }
}

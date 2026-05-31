<?php

namespace App\Temporal\Workflows;

use App\Modules\Order\Dto\WeeklyOrderInput;
use App\Temporal\Activities\NotificationActivity;
use App\Temporal\Activities\PaymentProviderActivity;
use App\Temporal\Activities\RestaurantActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class WeeklySubscriptionWorkflow
{
    /** @var NotificationActivity */
    private $notifications;

    /** @var RestaurantActivity */
    private $restaurant;

    /** @var PaymentProviderActivity */
    private $paymentProvider;

    public function __construct()
    {
        $this->notifications = Workflow::newActivityStub(
            NotificationActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(30))
        );

        $this->restaurant = Workflow::newActivityStub(
            RestaurantActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(30))
        );

        $this->paymentProvider = Workflow::newActivityStub(
            PaymentProviderActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(30))
        );
    }

    #[WorkflowMethod]
    public function handle(WeeklyOrderInput $input): \Generator
    {
        // 1. Get menu entity from Restaurant
        $menu = yield $this->restaurant->getWeeklyMenu($input->menuId());

        // 2. Charge payment
        yield $this->paymentProvider->charge(order: $input->getOrder(), amountInCents: $menu->priceInCents);

        // 3. Notify restaurant - book order
        $isBooked = yield $this->restaurant->bookWeeklyMenu($input->getOrder());

        if ($isBooked) {
            // 4. Notify customer - order confirmation
            $this->notifications->sendRestaurantConfirmationSms($input->getOrder()->customer_phone, $input->getOrder()->id);
            return;
        }
        // handle logic for when the restaurant is not available or something goes wrong
    }
}

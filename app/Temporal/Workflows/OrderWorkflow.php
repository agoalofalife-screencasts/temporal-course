<?php

namespace App\Temporal\Workflows;

use App\Modules\Order\Dto\OrderDto;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\SearchCourier\Dto\DeliveryLocation;
use App\Modules\SearchCourier\Dto\SearchCourierResult;
use App\Modules\SearchCourier\Entity\Courier;
use App\Temporal\Activities\NotificationActivity;
use App\Temporal\Activities\NotifyRestaurantActivity;
use App\Temporal\Activities\SearchCourier\CourierActivity;
use App\Temporal\Workflows\SearchCourier\FindCourierWorkflow;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Api\Enums\V1\ParentClosePolicy;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\Saga;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\TimerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

//  Marker for the Temporal SDK.
// Tells Temporal: "this class is a Workflow definition". The Temporal Worker scans classes with this attribute and registers them.
#[WorkflowInterface]
class OrderWorkflow
{
    private const RESTAURANT_TIMEOUT_SECONDS = 120; // 2 minutes

    /** @var NotifyRestaurantActivity */
    private $notifyRestaurantActivity;

    /** @var CourierActivity */
    private $courierActivity;

    /** @var NotificationActivity */
    private $notifications;

    private OrderStatus $status;

    private ?Courier $courier = null;

    public function __construct()
    {
        $this->notifications = Workflow::newActivityStub(
            NotificationActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::seconds(30))
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(3)),
        );

        $this->courierActivity = Workflow::newActivityStub(
            CourierActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::seconds(10))
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(3)),
        );

        $this->notifyRestaurantActivity = Workflow::newActivityStub(
            NotifyRestaurantActivity::class,
            ActivityOptions::new()
                //                ->withPriority()
                //  Observability — Instead of seeing a random UUID in the Temporal UI/logs, you see a meaningful identifier that's easy to trace back to a specific order or entity.
                //   In most cases you don't need to set it — Temporal auto-generates unique activity IDs within a workflow.
                //                ->withActivityId()

                //                ->withCancellationType(ActivityCancellationType::TryCancel)
                //  controls what happens to a running activity when its parent workflow is cancelled. It accepts one of three strategies:
                //1. WaitCancellationCompleted (default, value 0)
                //The workflow sends a cancellation request to the activity and waits until the activity acknowledges it and finishes cleanup. The activity must use heartbeating to receive the cancellation
                //signal. This can block the workflow for a long time if the activity ignores the request.
                //
                //2. TryCancel (value 1)
                //The workflow sends a cancellation request and immediately continues without waiting. The activity may still be running in the background, but the workflow doesn't care — it treats it as
                //cancelled right away.
                //
                //3. Abandon (value 2)
                //The workflow doesn't even send a cancellation request to the activity. It just immediately reports the activity as cancelled. The activity keeps running, unaware. (Note: currently not
                //supported.)
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withInitialInterval(CarbonInterval::seconds(5)) // first retry after 5 seconds
                        ->withBackoffCoefficient(2.0) // double each time
                        ->withMaximumInterval(CarbonInterval::seconds(30))
                        ->withMaximumAttempts(3)
                        ->withNonRetryableExceptions([
                            \InvalidArgumentException::class,
                        ]),
                )
                //                ->withTaskQueue()
                ->withSummary("Notify restaurant")
                ->withStartToCloseTimeout(CarbonInterval::seconds(10))
                ->withScheduleToStartTimeout(CarbonInterval::seconds(5)),
            //                ->withScheduleToCloseTimeout(CarbonInterval::seconds(2)) for online demonstration
        );
    }

    // Entry point of the Workflow.
    //  name — the Workflow type identifier in the Temporal Server. One class = one WorkflowMethod. When a client starts a Workflow,
    //  Temporal calls exactly this method.

    //  \Generator as the return type — this is a key point.
    //  The Temporal PHP SDK uses coroutines (generators) to pause execution.
    //  Each yield is a point where the Workflow can be suspended and later resumed
    #[WorkflowMethod(name: "Order")]
    public function handle(OrderDto $orderDto): \Generator
    {
        $saga = new Saga();

        try {
            // When compensate() is called, run all compensations in parallel (not sequentially)
            // Set to false if you need strict reverse-order execution
//        $saga->setParallelCompensation(true);

            $this->status = $orderDto->status;

            /**
             * Generate a UUID for the order.
             *
             * DO NOT use: Str::uuid(), Ramsey\Uuid, random_bytes()
             * These functions will produce different results on replay!
             *
             * Workflow::uuid() — deterministic UUID.
             * On replay it will return the same UUID as the first time.
             */
            $orderId = Workflow::uuid();

            /**
             * Get the current time.
             *
             * DO NOT use: now(), time(), Carbon::now()
             *
             * Workflow::now() — deterministic time.
             * Returns the time when the current workflow "step" started.
             */
            $startedAt = Workflow::now();

            // Log the start (will be visible in the Temporal UI)
            Workflow::getLogger()->info("Starting order workflow", [
                "order_id" => $orderId,
                "customer" => $orderDto->customerName(),
            ]);

            yield $this->notifyRestaurantActivity->notify($orderDto);

            // After successfully notifying the restaurant, register compensation to cancel the order
            // HAS TO BE IDEMPOTENT
            $saga->addCompensation(
                fn() => (yield $this->notifyRestaurantActivity->cancel($orderDto)),
            );

            // for simplicity, encapsulate it inside object
            $this->status = OrderStatus::RestaurantProcessing;

            Workflow::getLogger()->info("Restaurant was notified about new order");

            $restaurantIsResponded = (yield Workflow::awaitWithTimeout(
                CarbonInterval::seconds(self::RESTAURANT_TIMEOUT_SECONDS),
                fn(): bool => !$this->status->restaurantProcessing(),
            ));

            if (!$restaurantIsResponded) {
                Workflow::getLogger()->info(
                    "Restaurant did not respond to the order",
                );
                // might be the reason - for example rejected because timeout or some other reason
                $this->status = OrderStatus::RestaurantRejected;
                // Compensate: cancel the order at the restaurant
                yield $saga->compensate();
                return;
            }

            if ($this->status->restaurantRejected()) {
                $this->status = OrderStatus::RestaurantRejected;
                Workflow::getLogger()->info("Restaurant rejected the order");
                // Compensate: cancel the order at the restaurant
//                yield $saga->compensate();
                return;
            }

            $this->status = OrderStatus::RestaurantAccepted;

            // notify customer about the acceptance
            Workflow::getLogger()->info("Restaurant accepted the order");


            $version = yield Workflow::getVersion(
                'sms-after-restaurant-confirm',      // understandable name of changes
                Workflow::DEFAULT_VERSION,        // min supported
                1                                 // current version (max version)
            );

            if ($version >= 1) {
                // only for new workflows
                yield $this->notifications->sendOrderConfirmationSms(
                    $orderDto->customerPhone(),
                    $orderDto->orderId(),
                );
            }

            // Create stub for child workflow
            $findCourierWorkflow = Workflow::newChildWorkflowStub(
                FindCourierWorkflow::class,
                ChildWorkflowOptions::new()
                    // Unique id for child workflow
                    // easier to look for in UI and idempotency
                    ->withWorkflowId("find-courier-{$orderDto->orderId()}")

                    // Timeout for the entire process of finding a courier including retries
                    ->withWorkflowExecutionTimeout(CarbonInterval::minutes(30))

                    // What to do if parent workflow is closed
                    // PARENT_CLOSE_POLICY_TERMINATE - Child is being forcefully terminated
                    // PARENT_CLOSE_POLICY_ABANDON - Child continues to run
                    // PARENT_CLOSE_POLICY_REQUEST_CANCEL -  Child receives a termination signalIf a graceful shutdown is required
                    ->withParentClosePolicy(
                        ParentClosePolicy::PARENT_CLOSE_POLICY_REQUEST_CANCEL,
                    ),
            );

            $this->status = OrderStatus::CourierSearching;

            /**
             * @var SearchCourierResult $searchCourierResult
             */
            $searchCourierResult = (yield $findCourierWorkflow->find(
                pickup: new DeliveryLocation(
                    latitude: 40.7128,
                    longitude: -74.006,
                    address: "123 Restaurant Street, New York, NY 10001",
                ),
                dropOff: new DeliveryLocation(
                    latitude: 40.7589,
                    longitude: -73.9851,
                    address: "456 Customer Avenue, New York, NY 10019",
                ),
            ));

            if (!$searchCourierResult->courierWasFound()) {
                $this->status = OrderStatus::CourierWasNotFound;
                Workflow::getLogger()->info(
                    "Courier was not found, compensating...",
                );
                // Compensate: cancel the restaurant order (runs all registered compensations in reverse)
                yield $saga->compensate();
                return;
            }

            $this->status = OrderStatus::CourierAssigned;
            $this->courier = $searchCourierResult->courier;

            // After courier is assigned, register compensation to cancel the courier
            $saga->addCompensation(
                fn() => (yield $this->courierActivity->cancelCourier(
                    $this->courier->id,
                )),
            );

            yield Workflow::timer(
                CarbonInterval::minutes(2),
                TimerOptions::new()->withSummary('imitate delivery time')
            );
        } catch (CanceledFailure $e) {
            yield $saga->compensate();
        }
    }

    #[SignalMethod]
    public function restaurantConfirmation(bool $isConfirmed): void
    {
        // what happen if exception was thrown here? Demonstrated in video
        //        throw new \RuntimeException('Not implemented yet');

        if ($this->status !== OrderStatus::RestaurantProcessing) {
            // idempotent
            return;
        }
        if ($isConfirmed) {
            $this->status = OrderStatus::RestaurantAccepted;
        } else {
            $this->status = OrderStatus::RestaurantRejected;
        }
    }
    #[Workflow\QueryMethod]
    public function getStatus(): OrderStatus
    {
        return $this->status;
    }
}

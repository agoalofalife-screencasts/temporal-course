<?php

namespace App\Temporal\Workflows;

use App\Modules\Order\Dto\OrderDto;
use App\Temporal\Activities\NotifyRestaurantActivity;
use App\Temporal\Activities\PrepareOrderActivity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

//  Marker for the Temporal SDK.
// Tells Temporal: "this class is a Workflow definition". The Temporal Worker scans classes with this attribute and registers them.
#[WorkflowInterface]
class OrderWorkflow
{
    /** @var NotifyRestaurantActivity */
    private $notifyRestaurantActivity;

    /** @var PrepareOrderActivity */
    private $prepareOrderActivity;

    public function __construct()
    {
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
                ->withRetryOptions(RetryOptions::new()
                    ->withInitialInterval(CarbonInterval::seconds(5)) // first retry after 5 seconds
                    ->withBackoffCoefficient(2.0) // double each time
                    ->withMaximumInterval(CarbonInterval::seconds(30))
                    ->withMaximumAttempts(3)
                    ->withNonRetryableExceptions([\InvalidArgumentException::class])
                )
//                ->withTaskQueue()
                ->withSummary('Notify restaurant about new order')
                ->withStartToCloseTimeout(CarbonInterval::seconds(10))
                ->withScheduleToStartTimeout(CarbonInterval::seconds(5))
//                ->withScheduleToCloseTimeout(CarbonInterval::seconds(2)) for online demonstration
        );

        $this->prepareOrderActivity = Workflow::newActivityStub(
            PrepareOrderActivity::class,
            ActivityOptions::new()
                ->withRetryOptions(RetryOptions::new()
                    ->withMaximumAttempts(3)
                )
                // Total time budget for the entire activity execution (including all stages).
                ->withStartToCloseTimeout(CarbonInterval::seconds(120))
                // Maximum silence between heartbeats. If Temporal doesn't receive a heartbeat
                // within this window it considers the activity stalled and schedules a retry.
                ->withHeartbeatTimeout(CarbonInterval::seconds(3))
                ->withSummary('Prepare order in the kitchen')
        );
    }

    // Entry point of the Workflow.
    //  name — the Workflow type identifier in the Temporal Server. One class = one WorkflowMethod. When a client starts a Workflow,
    //  Temporal calls exactly this method.

    //  \Generator as the return type — this is a key point.
    //  The Temporal PHP SDK uses coroutines (generators) to pause execution.
    //  Each yield is a point where the Workflow can be suspended and later resumed
    #[WorkflowMethod(name: 'Order')]
    public function handle(OrderDto $orderDto): \Generator
    {
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
        Workflow::getLogger()->info('Starting order workflow', [
            'order_id' => $orderId,
            'customer' => $orderDto->customerName(),
        ]);

        try {
            yield $this->notifyRestaurantActivity->notify($orderDto);
        } catch (ActivityFailure $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof TimeoutFailure) {
                Workflow::timer(CarbonInterval::seconds(4), Workflow\TimerOptions::new()->withSummary('Notify user order was cancelled'));
                return;
            }
        }


        Workflow::getLogger()->info('Restaurant was notified about new order');

        $preparationResult = yield $this->prepareOrderActivity->prepare($orderDto);

        Workflow::getLogger()->info('Order preparation completed', [
            'status' => $preparationResult,
        ]);
    }
}

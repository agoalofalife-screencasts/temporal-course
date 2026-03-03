<?php

namespace App\Temporal\Activities;

use App\Modules\Order\Dto\OrderDto;
use Illuminate\Support\Facades\Log;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'PrepareOrder')]
class PrepareOrderActivity
{
    /**
     * Kitchen preparation stages in order.
     * The activity progresses through each stage, reporting heartbeats
     * so Temporal knows it's still alive and can resume from the right point on failure.
     */
    private const STAGES = [
        'accepted',
        'preparing',
        'cooking',
        'packaging',
        'ready_for_pickup',
    ];

    #[ActivityMethod(name: 'Prepare')]
    public function prepare(OrderDto $orderDto): string
    {
        $attempt = Activity::getInfo()->attempt;

        // On retry, resume from the last heartbeat stage instead of starting over.
        // Activity::getHeartbeatDetails() returns the last value passed to Activity::heartbeat().
        // On the first attempt this returns null — so we start from stage 0.
        $lastCompletedStage = Activity::getHeartbeatDetails();
        $startFrom = $lastCompletedStage !== null ? (int) $lastCompletedStage : 0;

        Log::info('PrepareOrder started', [
            'order_id' => $orderDto->orderId(),
            'attempt' => $attempt,
            'resuming_from_stage' => self::STAGES[$startFrom] ?? 'beginning',
        ]);

        for ($i = $startFrom; $i < count(self::STAGES); $i++) {
            $stage = self::STAGES[$i];

            Log::info("PrepareOrder stage: {$stage}", [
                'order_id' => $orderDto->orderId(),
                'stage_index' => $i,
                'attempt' => $attempt,
            ]);

            // Simulate failure during "cooking" on the first attempt to demonstrate
            // that the next attempt will resume from "cooking" (skipping earlier stages).
            if ($stage === 'cooking' && $attempt === 1) {
                throw new \RuntimeException(
                    "Kitchen equipment malfunction during '{$stage}' (attempt {$attempt})"
                );
            }

            // Simulate real work — each stage takes a few seconds.
            sleep(2);

            // Report progress to Temporal.
            // If we crash after this call, the next attempt picks up from stage $i+1
            // because getHeartbeatDetails() will return the value we passed here.
            Activity::heartbeat($i + 1);
        }

        Log::info('PrepareOrder completed — order is ready for pickup', [
            'order_id' => $orderDto->orderId(),
        ]);

        return 'ready_for_pickup';
    }
}

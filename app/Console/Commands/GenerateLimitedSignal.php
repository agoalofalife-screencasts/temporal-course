<?php

namespace App\Console\Commands;

use App\Modules\Offers\PromotionOffer;
use App\Temporal\Workflows\RestaurantPromotionWorkflow;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;
use Temporal\Client\WorkflowClient;

class GenerateLimitedSignal extends Command
{
    const int SIGNAL_LIMIT = 10_000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:limited-signals {signalCount}';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowClient $client)
    {
        $signalCount = (int)$this->argument('signalCount');

        if ($signalCount <= 0) {
            $this->error('Signal count must be greater than 0.');
            return;
        }

        if ($signalCount > self::SIGNAL_LIMIT) {
            $this->error("Signal count must be less than or equal to " . self::SIGNAL_LIMIT);
            return;
        }

        /** @var string $workflowName */
        $workflowName = $this->choice(
            'Choose possible workflow',
            [RestaurantPromotionWorkflow::class],
            0
        );

        $count = 0;

        /** @var RestaurantPromotionWorkflow $workflow */
        $workflow = $client->newRunningWorkflowStub($workflowName, 'restaurant-promotion-workflow');

        for ($i = 0; $i < $signalCount; $i++) {
            $workflow->addOffer(new PromotionOffer(
                    id: Uuid::uuid7(),
                    restaurantName: fake()->company(),
                    title: fake()->title(),
                    description: fake()->text(),
                    discountPercent: fake()->numberBetween(1, 100),
                    validUntil: now()->addDays(30)->toImmutable(),
                    createdAt: now()->toImmutable(),
                )
            );

            $count += 1;
            $this->info("Sent {$count} signal");
        }

        $this->info("Well done!");
    }
}

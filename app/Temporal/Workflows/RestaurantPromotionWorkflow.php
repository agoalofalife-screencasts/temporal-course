<?php

namespace App\Temporal\Workflows;

use App\Modules\Offers\PromotionOffer;
use App\Temporal\Activities\PromotionActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class RestaurantPromotionWorkflow
{
    /** @var PromotionActivity */
    private $storageOffersActivity;

    /**
     * @var array<PromotionOffer>
     */
    private array $pendingOffers = [];

    public function __construct()
    {
        $this->storageOffersActivity = Workflow::newActivityStub(
            PromotionActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(30))
            ->withRetryOptions(
             RetryOptions::new()
                 ->withMaximumAttempts(3)
                 ->withBackoffCoefficient(2.0)
            )
        );
    }

    #[WorkflowMethod]
    public function handle(): \Generator
    {
        while (true) {
            yield Workflow::await(fn () => count($this->pendingOffers) > 0);

            yield $this->handlePendingOffers();
            yield $this->createContinueAsNewIfNeed();
        }
    }

    private function handlePendingOffers(): \Generator
    {
        if (empty($this->pendingOffers)) {
            return;
        }
        Workflow::getLogger()->info('Offers to process: ' . count($this->pendingOffers));

        // store the offers in the database

        yield $this->storageOffersActivity->processOffers($this->pendingOffers);
        $this->pendingOffers = [];
    }

    private function createContinueAsNewIfNeed()
    {
        if (Workflow::getInfo()->shouldContinueAsNew) {

            yield Workflow::await(fn () => Workflow::allHandlersFinished());

            Workflow::continueAsNew(
                Workflow::getInfo()->type->name,
                []
            );
        }
    }

    #[SignalMethod]
    public function addOffer(PromotionOffer $offer): void
    {
        $this->pendingOffers[] = $offer;

        Workflow::getLogger()->debug('Offer received', [
            'offer_id' => $offer->id,
            'restaurant_name' => $offer->restaurantName,
        ]);
    }
}

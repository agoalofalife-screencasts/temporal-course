<?php

namespace App\Temporal\Activities;

use App\Modules\Offers\PromotionOffer;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface(prefix: "Promotions.")]
class PromotionActivity
{
    /**
     * @param array<PromotionOffer $offers
     * @return VirtualPromise<void>
     */
    #[ActivityMethod]
    public function processOffers(array $offers): void
    {
        foreach ($offers as $offer) {
            // saving offer to database
            // log
        }
    }
}

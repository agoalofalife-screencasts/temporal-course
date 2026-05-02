<?php

namespace App\Temporal\Activities\SearchCourier;

use App\Modules\SearchCourier\Dto\DeliveryLocation;
use App\Modules\SearchCourier\Entity\Courier;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Support\VirtualPromise;

#[ActivityInterface]
class CourierActivity
{
    /**
     * @return array<int, string>
     */
    #[ActivityMethod]
    public function findAvailableCouriersInCompanyA(
        DeliveryLocation $pickup,
        int $currentRadius,
        array $declinedCouriersIds,
    ): array {
        sleep(20);

        // list of candidates
        return ["id courier from company A"];
    }

    public function findAvailableCouriersInCompanyB(
        DeliveryLocation $pickup,
        int $currentRadius,
        array $declinedCouriersIds,
    ): array {
        sleep(15);

        // list of candidates
        return [
                //            'id courier from company B',
            ];
    }

    public function findAvailableCouriersInCompanyC(
        DeliveryLocation $pickup,
        int $currentRadius,
        array $declinedCouriersIds,
    ): array {
        sleep(1);
        //        throw new \RuntimeException('No couriers available in Company C');
        // list of candidates
        return [
                //            'id courier from company C',
            ];
    }

    /** @return VirtualPromise<void> */
    #[ActivityMethod]
    public function sendDeliveryOffers(
        array $availableCourierIds,
        DeliveryLocation $pickup,
        DeliveryLocation $dropOff,
    ): void {
        sleep(10);
    }

    #[ActivityMethod]
    public function getCourierInfo(string $acceptedCourierId): Courier
    {
        sleep(5);

        return new Courier(
            id: $acceptedCourierId,
            name: fake()->name(),
            phone: fake()->phoneNumber(),
            rating: fake()->numberBetween(1, 5),
        );
    }

    // Method to cancel pending offers for couriers that were not accepted
    /** @return VirtualPromise<void> */
    #[ActivityMethod]
    public function cancelPendingOffers(array $remainingCouriers): void
    {
        sleep(5);
    }

    /** @return VirtualPromise<void> */
    #[ActivityMethod]
    public function cancelCourier(string $courierId): void
    {
        $attempt = Activity::getInfo()->attempt;

        if ($attempt === 1) {
            throw new ApplicationFailure(
                message: "Courier service is unavailable",
                type: 'failure',
                nonRetryable: false,
                nextRetryDelay: CarbonInterval::seconds(30),
            );
        }

        sleep(1);

        Log::info("Cancelling courier", [
            "courier_id" => $courierId,
        ]);
    }
}

<?php

namespace App\Temporal\Activities\SearchCourier;

use App\Modules\SearchCourier\Dto\DeliveryLocation;
use App\Modules\SearchCourier\Entity\Courier;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Support\VirtualPromise;

#[ActivityInterface]
class CourierActivity
{
    #[ActivityMethod]
    /**
     * @return array<int, string>
     */
    public function findAvailableCouriersInCompanyA(DeliveryLocation $pickup, int $currentRadius, array $declinedCouriersIds): array
    {
        sleep(20);

        // list of candidates
        return [
            'id courier from company A',
        ];
    }

    public function findAvailableCouriersInCompanyB(DeliveryLocation $pickup, int $currentRadius, array $declinedCouriersIds): array
    {
        sleep(15);

        // list of candidates
        return [
//            'id courier from company B',
        ];
    }

    public function findAvailableCouriersInCompanyC(DeliveryLocation $pickup, int $currentRadius, array $declinedCouriersIds): array
    {
        sleep(1);
//        throw new \RuntimeException('No couriers available in Company C');
        // list of candidates
        return [
//            'id courier from company C',
        ];
    }

    #[ActivityMethod]
    /** @return VirtualPromise<void> */
    public function sendDeliveryOffers(
        array $availableCourierIds,
        DeliveryLocation $pickup,
        DeliveryLocation $dropOff,
    ): void
    {
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
    #[ActivityMethod]
    /** @return VirtualPromise<void> */
    public function cancelPendingOffers(array $remainingCouriers): void
    {
        sleep(5);
    }
}

<?php

declare(strict_types=1);

namespace App\Temporal\Workflows\SearchCourier;

use App\Modules\SearchCourier\Dto\CourierSearchStatusInfo;
use App\Modules\SearchCourier\Dto\DeliveryLocation;
use App\Modules\SearchCourier\Dto\SearchCourierResult;
use App\Modules\SearchCourier\Entity\Courier;
use App\Modules\SearchCourier\Enums\CourierSearchStatus;
use App\Temporal\Activities\SearchCourier\CourierActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;

// Workflow class for finding a courier for a delivery
class FindCourierWorkflow implements FindCourierWorkflowInterface
{
    private const int INITIAL_RADIUS_IN_METERS = 2000; // start radius of search in meters
    private const int MAX_RADIUS_IN_METERS = 10000; // max radius of search in meters

    private const int RADIUS_INCREMENT_IN_METERS = 2000; // increment of radius of search in meters

    private const int ACCEPT_TIMEOUT_MINUTES = 2; // timeout for accepting courier in minutes
    private const int MAX_ATTEMPTS = 5; // max attempts to find courier


    private CourierSearchStatus $status; // status of searching courier
    private int $currentRadius; // 2000, 4000, 6000, 8000, 10000
    private int $attemptNumber = 0;
    private int $couriersPinged = 0; // total number of couriers was reached

    private ?string $assignedCourierId = null; // id of the courier assigned to the delivery

    private array $declinedCourierIds = []; // list of couriers who declined the delivery by signal


    /** @var CourierActivity */
    private $courierActivity;

    public function __construct()
    {
        // init radius
        $this->currentRadius = self::INITIAL_RADIUS_IN_METERS;

        $this->courierActivity = Workflow::newActivityStub(
            CourierActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::minutes(2))
                ->withRetryOptions(
                    RetryOptions::new()->withmaximumAttempts(3)->withBackoffCoefficient(2.0)
                )
        );
    }


    /**
     * @param DeliveryLocation $pickup
     * @param DeliveryLocation $dropOff
     * @return \Generator<SearchCourierResult>
     */
    public function find(DeliveryLocation $pickup, DeliveryLocation $dropOff)
    {
        while ($this->attemptNumber < self::MAX_ATTEMPTS) {
            $this->attemptNumber += 1;

            $this->status = CourierSearchStatus::Searching;

            /** @var array<string> $availableCourierIds */
            $availableCourierIds = yield $this->courierActivity->findAvailableCouriers(
                $pickup->latitude,
                $pickup->longitude,
                $this->currentRadius,
                $this->declinedCourierIds // Except those who already declined
            );

            if (empty($availableCourierIds)) {
                // not found anyone, extend radius
                $this->expandRadius();
                continue;
            }

            $this->status = CourierSearchStatus::WaitingAcceptance;
            $this->couriersPinged += count($availableCourierIds);

            yield $this->courierActivity->sendDeliveryOffers(
                $availableCourierIds,
                $pickup,
                $dropOff,
            );

            $accepted = yield Workflow::awaitWithTimeout(
                CarbonInterval::minutes(self::ACCEPT_TIMEOUT_MINUTES),
                fn () => $this->assignedCourierId !== null,
            );

            if ($accepted) {
                $this->status = CourierSearchStatus::Found;

                /** @var Courier $courier */
                $courier = yield $this->courierActivity->getCourierInfo($this->assignedCourierId);

                yield $this->courierActivity->cancelPendingOffers(
                    array_diff($availableCourierIds, [$this->assignedCourierId])
                );

                return SearchCourierResult::courierFound($courier, $this->getSearchStatus());
            }
            $this->expandRadius(); // if timeout is over, expand the radius

        }
        $this->status = CourierSearchStatus::NotFound;

        return SearchCourierResult::courierNotFound($this->getSearchStatus());
    }

    public function courierAccepted(string $courierId): void
    {
        if ($this->assignedCourierId !== null) {
            return;
        }
        $this->assignedCourierId = $courierId;
    }

    public function courierDeclined(string $courierId): void
    {
        if (in_array($courierId, $this->declinedCourierIds, true)) {
            return;
        }
        $this->declinedCourierIds[] = $courierId;
    }

    public function getSearchStatus(): CourierSearchStatusInfo
    {
        return new CourierSearchStatusInfo(
            status: $this->status,
            currentRadius: $this->currentRadius,
            attemptNumber: $this->attemptNumber,
            couriersPinged: $this->couriersPinged,
            assignedCourierId: $this->assignedCourierId,
        );
    }

    private function expandRadius(): void
    {
        $this->currentRadius = min(
            $this->currentRadius + self::RADIUS_INCREMENT_IN_METERS,
            self::MAX_RADIUS_IN_METERS,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SearchCourier\Dto;
use App\Modules\SearchCourier\Enums\CourierSearchStatus;

class CourierSearchStatusInfo
{
    public function __construct(
        public readonly CourierSearchStatus $status,
        public readonly int                     $currentRadius, // current radius of search in meters
        public readonly int                     $attemptNumber, // the number of attempt
        public readonly int                     $couriersPinged, // the number of couriers pinged
        public readonly ?string                 $assignedCourierId = null,
    )
    {

    }
}

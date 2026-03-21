<?php

declare(strict_types=1);

namespace App\Modules\SearchCourier\Dto;

class DeliveryLocation
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $address,
    )
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SearchCourier\Entity;

class Courier
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly float $rating,
    )
    {

    }
}

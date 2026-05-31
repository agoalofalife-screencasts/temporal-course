<?php

declare(strict_types=1);

namespace App\Modules\Order\Dto;

final readonly class RestaurantMenu
{
    public function __construct(
        public string $name,
        public int $priceInCents,
//        public array $items <Items>
    )
    {

    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Offers;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;

class PromotionOffer
{
    public function __construct(
        public readonly UuidInterface $id,
        public readonly string $restaurantName,
        public readonly string $title,
        public readonly string $description,
        public readonly int $discountPercent,
        public readonly CarbonImmutable $validUntil,
        public readonly CarbonImmutable $createdAt,
    )
    {
    }
}

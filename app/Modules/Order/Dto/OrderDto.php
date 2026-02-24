<?php

declare(strict_types=1);

namespace App\Modules\Order\Dto;

use App\Models\Order;
use App\Modules\Order\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Keepsuit\LaravelTemporal\Integrations\LaravelData\TemporalSerializableCastAndTransformer;
use Ramsey\Uuid\UuidInterface;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

// Data from spaite needs to correct serialization and deserialization in temporal
final class OrderDto extends Data
{
    // properties has to be public  to work properly
    public function __construct(
        #[WithCast(TemporalSerializableCastAndTransformer::class)]
        #[WithTransformer(TemporalSerializableCastAndTransformer::class)]
        public Order            $order,
        public OrderStatus      $status,
        public ?CarbonImmutable $restaurantNotifiedAt = null,
        public ?CarbonImmutable  $restaurantConfirmedAt = null,
    )
    {

    }

    public function customerName(): string
    {
        return $this->order->customer_name;
    }

    public function orderId(): UuidInterface
    {
        return $this->order->id;
    }
}

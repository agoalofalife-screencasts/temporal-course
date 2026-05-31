<?php

declare(strict_types=1);

namespace App\Modules\Order\Dto;

use App\Models\Order;
use Keepsuit\LaravelTemporal\Integrations\LaravelData\TemporalSerializableCastAndTransformer;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class WeeklyOrderInput extends Data
{
    public function __construct(
        public string $menuId,
        #[WithCast(TemporalSerializableCastAndTransformer::class)]
        #[WithTransformer(TemporalSerializableCastAndTransformer::class)]
        public Order $order) // only public!
    {

    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function menuId(): string
    {
        return $this->menuId;
    }
}

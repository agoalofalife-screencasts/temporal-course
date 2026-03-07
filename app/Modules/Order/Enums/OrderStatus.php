<?php

namespace App\Modules\Order\Enums;
enum OrderStatus: string
{
    case Created = 'Created';
    case RestaurantProcessing = 'RestaurantProcessing';

    case RestaurantAccepted = 'RestaurantAccepted';

    case RestaurantRejected = 'RestaurantRejected';
    case Processing = 'Processing';
    case Completed = 'Completed';
    case Canceled = 'Canceled';


    public function restaurantRejected(): bool
    {
        return $this->value === self::RestaurantRejected->value;
    }

    public function restaurantReady(): bool
    {
        return $this->value === self::RestaurantAccepted->value;
    }

    public function restaurantProcessing(): bool
    {
        return $this->value === self::RestaurantProcessing->value;
    }
}

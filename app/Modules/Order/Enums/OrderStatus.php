<?php

namespace App\Modules\Order\Enums;
enum OrderStatus: string
{
    case Created = 'Created';
    case RestaurantProcessing = 'RestaurantProcessing';

    case RestaurantAccepted = 'RestaurantAccepted';

    case RestaurantRejected = 'RestaurantRejected';
    case CourierSearching = 'CourierSearching';

    case CourierAssigned = 'CourierAssigned';
    case CourierWasNotFound = 'CourierWasNotFound';

    case Processing = 'Processing';
    case Completed = 'Completed';
    case Canceled = 'Canceled';

    public function getHumanReadableStatus(): string
    {
        return match ($this) {
            self::Created => 'Order created',
            self::RestaurantProcessing => 'Waiting confirmation from restaurant',
            self::RestaurantAccepted => 'Restaurant confirmed the order',
            self::RestaurantRejected => 'Restaurant could not confirmed the order',
            self::CourierSearching => 'Searching for courier',
            self::CourierAssigned => 'Courier assigned',
            self::Completed => 'Order successfully completed',
            self::Canceled => 'Order was cancelled',
            default => 'Status is unknown',
        };
    }

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

    public function courierIsNotAssignedYet(): bool
    {
        return !in_array($this->value, [self::CourierSearching->value, self::CourierAssigned->value]);
    }
}

<?php

namespace App\Temporal\Activities;

use App\Models\Order;
use App\Modules\Order\Dto\RestaurantMenu;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface]
class RestaurantActivity
{
    #[ActivityMethod]
    public function getWeeklyMenu(string $menuId): RestaurantMenu
    {
        sleep(1);

        return new RestaurantMenu(
            name: fake()->name(),
            priceInCents: fake()->numberBetween(100, 1000),
        );
    }

    public function bookWeeklyMenu(Order $order): bool
    {
        sleep(1);
        // book order
        return true; // it might be result as object - for simplicity is primitive
    }
}

<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\RestaurantWebhookController;

Route::get('/', function () {
    return view('welcome');
});


Route::post('/orders', OrderController::class);

Route::post('/orders/{order}/states', [RestaurantWebhookController::class, 'restaurantConfirmation']);

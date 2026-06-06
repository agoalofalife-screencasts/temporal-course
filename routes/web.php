<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\RestaurantWebhookController;

Route::get('/', function () {
    return view('welcome');
});

// update address in order
Route::put('/orders/address', [OrderController::class, 'updateAddress']);
// update address in order asynchronously (example)
Route::put('/orders/address-async', [OrderController::class, 'asyncUpdateAddress']);
Route::get('/orders/{workflowId}/updates/{updateId}', [OrderController::class, 'getUpdateResult']);

Route::post('/orders', [OrderController::class, 'store']);

Route::post('/orders/{order}/states', [RestaurantWebhookController::class, 'restaurantConfirmation']);
Route::get('orders/{order}/statuses', [OrderStatusController::class, 'index']);

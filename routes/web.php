<?php

use App\Http\Controllers\OrderController;

Route::get('/', function () {
    return view('welcome');
});


Route::post('/orders', OrderController::class);

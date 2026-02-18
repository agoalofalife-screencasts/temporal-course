<?php

use App\Http\Controllers\HelloWorldController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/hello', HelloWorldController::class);

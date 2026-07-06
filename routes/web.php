<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::controller(TelegramController::class)->group(function () {
    Route::get('test-telegram', 'telegramTest');
});
<?php

use App\Http\Controllers\UserController;
use App\Http\Middleware\TokenVerificationMiddleware;
use Illuminate\Support\Facades\Route;

// Route::prefix('api')->group(function () {
    Route::post('/user-registration', [UserController::class, 'userRegistration']);
    Route::post('/user-login', [UserController::class, 'userLogin']);
    Route::post('/send-otp', [UserController::class, 'sendOtp']);
    Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('/reset-password', [UserController::class, 'resetPassword'])->middleware([TokenVerificationMiddleware::class]);

    Route::get('/logout', [UserController::class,'logout'])->middleware([TokenVerificationMiddleware::class]);
// });

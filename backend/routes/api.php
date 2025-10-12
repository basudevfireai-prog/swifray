<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\TokenVerificationMiddleware;
use Illuminate\Support\Facades\Route;


// Customer Routes
Route::post('/customer-registration', [CustomerController::class, 'customerRegistration']);
Route::post('/customer-login', [CustomerController::class, 'customerLogin']);
Route::post('/customer-send-otp', [CustomerController::class, 'customerSendOtp']);
Route::post('/customer-verify-otp', [CustomerController::class, 'customerVerifyOtp']);
Route::post('/customer-reset-password', [CustomerController::class, 'customerResetPassword'])->middleware([TokenVerificationMiddleware::class]);

Route::get('/customer-logout', [CustomerController::class,'customerLogout'])->middleware([TokenVerificationMiddleware::class]);

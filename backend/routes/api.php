<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DriverController;
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

// Driver Routes
Route::post('/driver-registration', [DriverController::class, 'driverRegistration']);
Route::post('/driver-login', [DriverController::class, 'driverLogin']);
Route::post('/driver-send-otp', [DriverController::class, 'driverSendOtp']);
Route::post('/driver-verify-otp', [DriverController::class, 'driverVerifyOtp']);
Route::post('/driver-reset-password', [DriverController::class, 'driverResetPassword'])->middleware([TokenVerificationMiddleware::class]);

Route::get('/driver-logout', [DriverController::class,'driverLogout'])->middleware([TokenVerificationMiddleware::class]);

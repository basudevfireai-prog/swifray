<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\TokenVerificationMiddleware;
use Illuminate\Support\Facades\Route;


// Customer Routes
Route::post('/customer-registration', [CustomerController::class, 'customerRegistration']);
Route::post('/customer-send-otp', [CustomerController::class, 'customerSendOtp']);
Route::post('/customer-verify-otp', [CustomerController::class, 'customerVerifyOtp']);
Route::post('/customer-reset-password', [CustomerController::class, 'customerResetPassword'])->middleware([ TokenVerificationMiddleware::class]);

Route::get('/customer-logout', [CustomerController::class,'customerLogout'])->middleware([TokenVerificationMiddleware::class]);

// Driver Routes
Route::post('/driver-registration', [DriverController::class, 'driverRegistration']);
Route::post('/driver-send-otp', [DriverController::class, 'driverSendOtp']);
Route::post('/driver-verify-otp', [DriverController::class, 'driverVerifyOtp']);
Route::post('/driver-reset-password', [DriverController::class, 'driverResetPassword'])->middleware([ TokenVerificationMiddleware::class]);

Route::get('/driver-logout', [DriverController::class,'driverLogout'])->middleware([TokenVerificationMiddleware::class]);

Route::post('login', [AuthController::class, 'login']);

Route::post('/update-status', [AuthController::class,'updateStatus'])->middleware([TokenVerificationMiddleware::class]);
Route::get('/get-status', [AuthController::class,'getStatus'])->middleware([TokenVerificationMiddleware::class]);

Route::middleware([TokenVerificationMiddleware::class])->group(function () {
    // Customer Specific Routes
    Route::prefix('customer')->group(function () {
        Route::get('/profile', [CustomerController::class, 'getProfile']);
        Route::get('/orders', [CustomerController::class, 'getOrderHistory']);
        // ... other customer APIs
    });

    // Driver Specific Routes
    Route::prefix('driver')->group(function () {
        Route::get('/profile', [DriverController::class, 'getProfile']);
        Route::post('/status', [DriverController::class, 'updateStatus']);
        Route::get('/earnings', [DriverController::class, 'getEarnings']);
        // ... other driver APIs
    });

    // CUSTOMER API ROUTES (Booking, Payment, Tracking)
    Route::prefix('customer/orders')->group(function () {
        Route::post('/', [OrderController::class, 'bookDelivery']);
        Route::post('/{id}/pay', [OrderController::class, 'completePayment']);
        Route::get('/{id}/tracking', [OrderController::class, 'getOrderTracking']);
        Route::get('/{id}/proof', [OrderController::class, 'getProofOfDelivery']);
    });

    // DRIVER API ROUTES (Job Management)
    Route::prefix('driver/jobs')->group(function () {
        Route::get('/available', [OrderController::class, 'listAvailableJobs']);
        Route::post('/{id}/action', [OrderController::class, 'handleJobAction']); // Accepts or Rejects
        Route::post('/{id}/pickup', [OrderController::class, 'confirmPickup']);
        Route::post('/{id}/complete', [OrderController::class, 'confirmDelivery']);
        // Route::post('/{id}/location', ...) // Driver location update endpoint (usually handled via WebSockets/Pusher for real-time, but a simple API update can work for MVP)
    });
});


<?php

namespace App\Http\Controllers;

use App\Helper\JWTToken;
use App\Mail\OTPMail;
use App\Models\Driver;
use App\Models\DriverEarning;
use App\Models\DriverProfile;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DriverController extends Controller
{
     public function driverRegistration(Request $request) {
        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:255",
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Use DB transaction to ensure atomicity
        DB::beginTransaction();

        try {
            // Step 1: create user
            $user = User::create([
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'phone' => $request->input('phone'),
            ]);

            // Step 2: create customer linked to user
            $driver = Driver::create([
                'user_id' => $user->id,
                'rating_avg' => 0,
                'verification_status' => 'pending',
                'payout_account_id' => Str::uuid(),
            ]);

            // Assign role to user (Spatie package)
            // $userRole = User::where('id', $driver->user_id)->first();
            $userRole = User::where('id', $driver->user_id)->first();
            $userRole->assignRole('driver');

            // Create Profile
            DriverProfile::create(['user_id' => $user->id]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Driver registered successfully',
                'data' => [
                    'user' => $user,
                    'driver' => $driver,
                ],
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong during registration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function driverLogin(Request $request) {
    //     try{
    //         $driver = Driver::whereHas('user', function($query) use ($request) {
    //             $query->where('email', $request->input('email'));
    //         })->with('user')->first();

    //         if($driver !== null && Hash::check($request->input('password'), $driver->user->password)){
    //             $driver_token = JWTToken::createToken($request->input('email'), $driver->user->id);

    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'Driver logged in successfully',
    //             ], 200)->cookie('driver_token', $driver_token, 60*24*30);
    //         }

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid credentials',
    //         ], 401);

    //     }catch(Exception $e){
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Login failed',
    //         ], 500);
    //     }
    // }

    public function driverSendOtp(Request $request){
        try {
            $email = $request->input('email');

            // 1️⃣ Find customer with related user
            $driver = Driver::whereHas('user', function ($query) use ($email) {
                $query->where('email', $email);
            })->with('user')->first();

            if (!$driver) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }

            // 2️⃣ Check if an OTP already exists and is still valid
            if ($driver->user->otp && $driver->user->otp_expires_at && Carbon::parse($driver->user->otp_expires_at)->isFuture()) {
                // If still valid, prevent resending immediately
                $remaining = round(now()->diffInSeconds(Carbon::parse($driver->user->otp_expires_at)));
                return response()->json([
                    'status' => 'failed',
                    'message' => "An OTP has already been sent. Please use it or wait {$remaining} seconds until it expires."
                ], 429);
            }

            // 3️⃣ Optional: Cooldown check (e.g., prevent sending again within 60 sec)
            // if ($driver->user->otp_last_sent_at && $driver->user->otp_last_sent_at->diffInSeconds(now()) < 60) {
            //     $wait = 60 - $driver->user->otp_last_sent_at->diffInSeconds(now());
            //     return response()->json([
            //         'status' => 'failed',
            //         'message' => "Please wait {$wait} seconds before requesting another OTP."
            //     ], 429);
            // }

            // 4️⃣ Generate new 6-digit OTP
            $otp = rand(100000, 999999);

            // 5️⃣ Store OTP + expiry (5 minutes) + last sent time
            $driver->user->update([
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
                'otp_last_sent_at' => now(),
            ]);

            // 6️⃣ Send OTP email
            Mail::to($email)->send(new OTPMail($otp));

            return response()->json([
                'status' => 'success',
                'message' => '6-digit OTP has been sent to your email. It will expire in 5 minutes.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to send OTP! ' . $e->getMessage(),
            ], 500);
        }
    }

    public function driverVerifyOtp(Request $request){
        try{
            $driver = Driver::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if(!$driver){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);

            }elseif ($driver->user->otp_expires_at && now()->greaterThan($driver->user->otp_expires_at)) {
                // OTP update
                $driver->user->otp = 0;
                $driver->user->otp_expires_at = null;
                $driver->user->otp_last_sent_at = null;
                $driver->user->save();

                // Check expiry time
                return response()->json([
                    'status' => 'failed',
                    'message' => 'OTP has expired! Please request a new one.'
                ], 400);

            }elseif($driver->user->otp !== $request->input('otp')){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid OTP!'
                ], 400);

            }else{
                // OTP update
                $driver->user->otp = 0;
                $driver->user->otp_expires_at = null;
                $driver->user->otp_last_sent_at = null;
                $driver->user->save();

                $token = JWTToken::passwordResetToken($request->input('email'), $driver->user_id);

                return response()->json([
                    'status' => 'success',
                    'message' => 'OTP verified successfully',
                ], 200)->cookie('token', $token, 60); // Token valid for 60 minutes
            }
        }catch(Exception $e){

            return response()->json([
                'status' => 'failed',
                'message' => 'OTP verification failed!'
            ], 500);

        }
    }

    public function driverResetPassword(Request $request){
        try{

            $email = $request->header('email');
            $password = $request->input('password');

            $driver = Driver::whereHas('user', function($query) use ($email) {
                $query->where('email', $email);
            })->update(['password' => Hash::make($password)]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successfully'
            ], 200);

        }catch(Exception $e){
            return response()->json([
                'status' => 'failed',
                'message' => 'Password reset failed!'
            ], 500);
        }
    }

    public function driverLogout(){
        return response()->json([
            'status' => 'success',
            'message' => 'Driver logged out successfully'
        ], 200)->cookie('driver_token', '', -1);
    }

    /**
     * Get the authenticated driver's profile and availability status.
     */
    public function getProfile(Request $request)
    {
        try {
            $driverID = $request->header('id'); // ID set by TokenVerificationMiddleware

            $profile = DriverProfile::where('user_id', $driverID)
                ->with('user')
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $profile
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Driver profile not found.'
            ], 404);
        }
    }

    /**
     * Update the driver's availability status.
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $driverID = $request->header('id');
        $isAvailable = $request->input('is_available');

        $profile = DriverProfile::where('user_id', $driverID)->first();

        if (!$profile) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Driver profile not found.'
            ], 404);
        }

        // Only allow to become available if documents are verified
        if ($isAvailable && $profile->document_status !== 'verified') {
            return response()->json([
                'status' => 'failed',
                'message' => 'Cannot go online. Documents are not yet verified.'
            ], 403);
        }

        $profile->is_available = $isAvailable;
        $profile->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Availability updated.',
            'is_available' => $profile->is_available
        ], 200);
    }

    /**
     * Get the driver's total earnings and job history.
     */
    public function getEarnings(Request $request)
    {
        $driverID = $request->header('id');

        $earnings = DriverEarning::where('driver_id', $driverID)
            ->with('order')
            ->get();

        $totalEarnings = $earnings->where('status', 'paid')->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_earnings' => $totalEarnings,
                'job_history' => $earnings
            ]
        ], 200);
    }

    // Additional methods (job lists, accepting jobs, confirmation) would be placed in OrderController

}

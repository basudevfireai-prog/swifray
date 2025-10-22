<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helper\JWTToken;
use App\Mail\OTPMail;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function customerRegistration(Request $request) {
        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:255",
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone' => 'required|unique:users,phone',
            'type' => 'required|string',
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
            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $request->input('name'),
                'type' => $request->input('type'),
                'billing_account_id' => Str::uuid(), // example unique billing ID
            ]);

            // Assign role to user (Spatie package)
            $userRole = User::where('id', $customer->user_id)->first();
            $userRole->assignRole('customer');

            // Create Profile
            CustomerProfile::create(['user_id' => $user->id]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer registered successfully',
                'data' => [
                    'user' => $user,
                    'customer' => $customer,
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

    // public function customerLogin(Request $request) {
    //     try{
    //         $customer = Customer::whereHas('user', function($query) use ($request) {
    //             $query->where('email', $request->input('email'));
    //         })->with('user')->first();

    //         if($customer !== null && Hash::check($request->input('password'), $customer->user->password)){
    //             $customer_token = JWTToken::createToken($request->input('email'), $customer->user->id);

    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'Login successful',
    //             ], 200)->cookie('customer_token', $customer_token, 60*24*30);
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

    public function customerSendOtp(Request $request){
        try {
            $email = $request->input('email');

            // 1️⃣ Find customer with related user
            $customer = Customer::whereHas('user', function ($query) use ($email) {
                $query->where('email', $email);
            })->with('user')->first();

            if (!$customer) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }

            // 2️⃣ Check if an OTP already exists and is still valid
            if ($customer->user->otp && $customer->user->otp_expires_at && Carbon::parse($customer->user->otp_expires_at)->isFuture()) {
                // If still valid, prevent resending immediately
                $remaining = round(now()->diffInSeconds(Carbon::parse($customer->user->otp_expires_at)));
                return response()->json([
                    'status' => 'failed',
                    'message' => "An OTP has already been sent. Please use it or wait {$remaining} seconds until it expires."
                ], 429);
            }

            // 3️⃣ Optional: Cooldown check (e.g., prevent sending again within 60 sec)
            // if ($customer->user->otp_last_sent_at && $customer->user->otp_last_sent_at->diffInSeconds(now()) < 60) {
            //     $wait = 60 - $customer->user->otp_last_sent_at->diffInSeconds(now());
            //     return response()->json([
            //         'status' => 'failed',
            //         'message' => "Please wait {$wait} seconds before requesting another OTP."
            //     ], 429);
            // }

            // 4️⃣ Generate new 6-digit OTP
            $otp = rand(100000, 999999);

            // 5️⃣ Store OTP + expiry (5 minutes) + last sent time
            $customer->user->update([
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

    public function customerVerifyOtp(Request $request){
        try{
            $customer = Customer::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if(!$customer){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);

            }elseif ($customer->user->otp_expires_at && now()->greaterThan($customer->user->otp_expires_at)) {
                // OTP update
                $customer->user->otp = 0;
                $customer->user->otp_expires_at = null;
                $customer->user->otp_last_sent_at = null;
                $customer->user->save();

                // Check expiry time
                return response()->json([
                    'status' => 'failed',
                    'message' => 'OTP has expired! Please request a new one.'
                ], 400);

            }elseif($customer->user->otp !== $request->input('otp')){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid OTP!'
                ], 400);

            }else{
                // OTP update
                $customer->user->otp = 0;
                $customer->user->otp_expires_at = null;
                $customer->user->otp_last_sent_at = null;
                $customer->user->save();

                $token = JWTToken::passwordResetToken($request->input('email'), $customer->user_id);

                return response()->json([
                    'status' => 'success',
                    'message' => 'OTP verified successfully',
                ], 200)->cookie('token', $token, 5); // Token valid for 5 minutes
            }
        }catch(Exception $e){

            return response()->json([
                'status' => 'failed',
                'message' => 'OTP verification failed!'
            ], 500);

        }
    }

    public function customerResetPassword(Request $request){
        try{

            $email = $request->header('email');
            $password = $request->input('password');

            $customer = Customer::whereHas('user', function($query) use ($email) {
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

    public function customerLogout(){
        return response()->json([
            'status' => 'success',
            'message' => 'Customer logged out successfully'
        ], 200)->cookie('customer_token', '', -1);
    }

    /**
     * Get the authenticated customer's profile.
     */
    public function getProfile(Request $request)
    {
        try {
            $customerID = $request->header('id'); // ID set by TokenVerificationMiddleware

            $user = CustomerProfile::where('user_id', $customerID)
                ->with('user') // Eager load the User model
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $user
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Customer profile not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'An error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Customer Delivery History (List of Orders).
     */
    public function getOrderHistory(Request $request)
    {
        $customerID = $request->header('id');

        $orders = Order::where('customer_id', $customerID)
            ->with('locations', 'tracking')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ], 200);
    }

    // Additional methods (booking, payment, tracking) would be placed in OrderController
}

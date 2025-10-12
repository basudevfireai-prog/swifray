<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helper\JWTToken;
use App\Mail\OTPMail;
use App\Models\Customer;
use App\Models\User;
use Exception;
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

    public function customerLogin(Request $request) {
        try{
            $customer = Customer::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if($customer !== null && Hash::check($request->input('password'), $customer->user->password)){
                $token = JWTToken::createToken($request->input('email'), $customer->user->id);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Login successful',
                ], 200)->cookie('token', $token, 60*24*30);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);

        }catch(Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
            ], 500);
        }
    }

    public function customerSendOtp(Request $request){
        try{
            $otp = rand(1000, 9999);
            $customer = Customer::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if(!$customer){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }else{
                // Send the OTP via email
                Mail::to($customer->user->email)->send(new OTPMail($otp));
                Customer::whereHas('user', function($query) use ($request) {
                    $query->where('email', $request->input('email'));
                })->update(['otp' => $otp]);

                return response()->json([
                    'status' => 'success',
                    'message' => '4 digit OTP code has been sent to your email'
                ], 200);
            }

        }catch(Exception $e){
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to send OTP!'
            ], 500);
        }
    }

    public function customerVerifyOtp(Request $request){
        try{
            // $user = User::where('email', $request->input('email'))->first();
            $customer = Customer::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if(!$customer){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }elseif($customer->user->otp !== $request->input('otp')){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid OTP!'
                ], 400);
            }else{
                // OTP is valid, proceed with the desired action (e.g., password reset)
                // Clear the OTP after successful verification

                $customer->user->otp = 0;
                $customer->user->save();

                $token = JWTToken::passwordResetToken($request->input('email'), $customer->user_id);

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
            'message' => 'Logged out successfully'
        ], 200)->cookie('token', '', -1);
    }
}

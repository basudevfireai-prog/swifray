<?php

namespace App\Http\Controllers;

use App\Helper\JWTToken;
use App\Mail\OTPMail;
use App\Models\Driver;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
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
                'role' => 'driver',

            ]);

            // Step 2: create customer linked to user
            $driver = Driver::create([
                'user_id' => $user->id,
                'rating_avg' => 0,
                'verification_status' => 'pending',
                'payout_account_id' => Str::uuid(),
            ]);

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

    public function driverLogin(Request $request) {
        try{
            $driver = Driver::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if($driver !== null && Hash::check($request->input('password'), $driver->user->password)){
                $token = JWTToken::createToken($request->input('email'), $driver->user->id);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Driver logged in successfully',
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

    public function driverSendOtp(Request $request){
        try{
            $otp = rand(1000, 9999);
            $driver = Driver::whereHas('user', function($query) use ($request) {
                $query->where('email', $request->input('email'));
            })->with('user')->first();

            if(!$driver){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }
            // Send the OTP via email
            Mail::to($driver->user->email)->send(new OTPMail($otp));
            // Driver::whereHas('user', function($query) use ($request) {
            //     $query->where('email', $request->input('email'));
            // })->update(['otp' => $otp]);
            User::where('id', $driver->user_id)->update(['otp' => $otp]);

            return response()->json([
                'status' => 'success',
                'message' => '4 digit OTP code has been sent to your email'
            ], 200);

        }catch(Exception $e){
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to send OTP!'
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

            }elseif($driver->user->otp !== $request->input('otp')){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid OTP!'
                ], 400);
            }else{
                // OTP update
                $driver->user->otp = 0;
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
            'message' => 'Logged out successfully'
        ], 200)->cookie('token', '', -1);
    }
}

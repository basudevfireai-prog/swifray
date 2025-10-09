<?php

namespace App\Http\Controllers;

use App\Helper\JWTToken;
use App\Mail\OTPMail;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function userRegistration(Request $request) {
        try{
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'phone' => 'required|string|max:15',
            ]);

        }catch(ValidationException $e){
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => $user,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
            ], 500);
        }
    }

    public function userLogin(Request $request) {
        try {
            $user = User::where('email', $request->input('email'))->first();

            if ($user !== null && Hash::check($request->input('password'), $user->password)) {
                $token = JWTToken::createToken($request->input('email'), $user->id);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Login successful',
                ], 200)->cookie('token', $token, 60*24*30);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
            ], 500);
        }

    }

    public function sendOtp(Request $request){
        try{
            $otp = rand(1000, 9999);
            $user = User::where('email', $request->input('email'))->first();

            if(!$user){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }else{
                // Send the OTP via email
                Mail::to($user->email)->send(new OTPMail($otp));
                User::where('email', $request->input('email'))->update(['otp' => $otp]);

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

    public function verifyOtp(Request $request){
        try{
            $user = User::where('email', $request->input('email'))->first();

            if(!$user){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email not found!'
                ], 404);
            }elseif($user->otp !== $request->input('otp')){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Invalid OTP!'
                ], 400);
            }else{
                // OTP is valid, proceed with the desired action (e.g., password reset)
                // Clear the OTP after successful verification
                $user->otp = 0;
                $user->save();

                $token = JWTToken::passwordResetToken($request->input('email'), $user->id);

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

    public function resetPassword(Request $request){
        try{

            $email = $request->header('email');
            $password = $request->input('password');

            User::where('email', $email)->update(['password' => Hash::make($password)]);

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

    public function logout(){
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200)->cookie('token', '', -1);
    }

}

<?php

namespace App\Http\Controllers;

use App\Helper\JWTToken;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request) {
       try {
           // Validate the request
           $request->validate([
               'email' => 'required|email',
               'password' => 'required|string|min:4',
           ]);

           $user = User::where('email', $request->input('email'))->first();

           if ($user->hasRole('customer') && Hash::check($request->input('password'), $user->password)) {

               // Generate JWT token
               $customer_token = JWTToken::createToken($request->input('email'), $user->id);

               return response()->json([
                   'status' => 'success',
                   'message' => 'Customer Login successful',
                   'user' => $user,
               ], 200)->cookie('customer_token', $customer_token, 60*24*30); // 30 day expiration

           }elseif ($user->hasRole('driver') && Hash::check($request->input('password'), $user->password)) {

               // Generate JWT token
               $driver_token = JWTToken::createToken($request->input('email'), $user->id);

               return response()->json([
                   'status' => 'success',
                   'message' => 'Driver Login successful',
                   'user' => $user,
               ], 200)->cookie('driver_token', $driver_token, 60*24*30); // 30 day expiration
           }elseif ($user->hasRole('admin') && Hash::check($request->input('password'), $user->password)) {

               // Generate JWT token
               $admin_token = JWTToken::createToken($request->input('email'), $user->id);

               return response()->json([
                   'status' => 'success',
                   'message' => 'Admin Login successful',
                   'user' => $user,
               ], 200)->cookie('admin_token', $admin_token, 60*24*30); // 30 day expiration
           }elseif ($user->hasRole('super-admin') && Hash::check($request->input('password'), $user->password)) {

               // Generate JWT token
               $super_admin_token = JWTToken::createToken($request->input('email'), $user->id);

               return response()->json([
                   'status' => 'success',
                   'message' => 'Super Admin Login successful',
                   'user' => $user,
               ], 200)->cookie('super_admin_token', $super_admin_token, 60*24*30); // 30 day expiration

           }else{

               return response()->json([
                       'status' => 'error',
                       'message' => 'Invalid credentials',
                   ], 401);

           }

       } catch (Exception $e) {
           return response()->json([
               'status' => 'error',
               'message' => 'An error occurred during login',
               'error' => $e->getMessage(),
           ], 500);
       }
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:available,busy',
        ]);

        $email = $request->header('email');
        $user = User::where('email', $email)->first();

        $user->status = $request->input('status');
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.',
            'data' => [
                'user_id' => $user->id,
                'status' => $user->status,
            ]
        ]);
    }

    public function getStatus(Request $request)
    {
        $email = $request->header('email');
        $user = User::where('email', $email)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'status' => $user->status,
            ]
        ]);
    }
}


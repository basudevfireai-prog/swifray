<?php

namespace App\Http\Middleware;

use App\Helper\JWTToken;
use Closure;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenVerificationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isAuthenticated = false;

        if($request->hasCookie('customer_token')){

            $token = $request->cookie('customer_token');
            $customer_result = JWTToken::VerifyToken($token);

            if ($customer_result != "unauthorized") {
                $request->headers->set('email', $customer_result->userEmail);
                $request->headers->set('id', $customer_result->userID);
                $isAuthenticated = true;
            }

        } elseif ($request->hasCookie('driver_token')) {

            $token = $request->cookie('driver_token');
            $driver_result = JWTToken::VerifyToken($token);

            if ($driver_result != 'unauthorized') {
                $request->headers->set('email', $driver_result->userEmail);
                $request->headers->set('id', $driver_result->userID);
                $isAuthenticated = true;
            }
        }

        // If authenticated, proceed
        if ($isAuthenticated) {
            return $next($request);
        }

        // If not authenticated (no token or unauthorized token), return 401 JSON
        return response()->json([
            'status' => 'failed',
            'message' => 'Unauthorized Access. Please log in.',
        ], 403);

    }
}

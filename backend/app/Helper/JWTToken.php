<?php
namespace App\Helper;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTToken
{
    public static function CreateToken($userEmail,$userID):string{
        $key =env('JWT_KEY');
        $payload=[
            'iss'=>'laravel-token',
            'iat'=>time(),
            'exp'=>time()+60*60,
            'userEmail'=>$userEmail,
            'userID'=>$userID
        ];
       return JWT::encode($payload,$key,'HS256');
    }

    public static function CreateTokenForSetPassword($userEmail, $userID = '0'):string{
        $key =env('JWT_KEY');
        $payload=[
            'iss'=>'laravel-token',
            'iat'=>time(),
            'exp'=>time()+60*20,
            'userEmail'=>$userEmail,
            'userID'=> $userID
        ];
        return JWT::encode($payload,$key,'HS256');
    }

    public static function VerifyToken($token):string|object
    {
        try {
            if($token==null){
                return 'unauthorized';
            }

            $key =env('JWT_KEY');
            $decode=JWT::decode($token,new Key($key,'HS256'));
            return $decode;
        }
        catch (Exception $e){
            return 'unauthorized';
        }
    }

    public static function passwordResetToken($userEmail, $userID) {

        $key = env("JWT_KEY");
        $payload = [
            'iss' => 'laravel-token', // Issuer
            'iat' => time(), // Issued at
            'exp' => time() + 60*20, // Expiration time
            'userEmail' => $userEmail,
            'userID' => '0',
        ];

        $token = JWT::encode($payload, $key, 'HS256');

        return $token;
    }
}

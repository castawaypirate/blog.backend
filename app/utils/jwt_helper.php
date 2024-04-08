<?php

require_once __DIR__.'/../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper
{
    private static $secretKey = JWT_SECRET; // Replace with your own secret key

    public static function generateToken($data)
    {
        $tokenId = base64_encode(random_bytes(32));
        $issuedAt = time();
        $expire = $issuedAt + JWT_EXPIRATION; // Token expires in 1 hour (adjust as needed)

        $data = array_merge($data, [
            'iat' => $issuedAt,
            'jti' => $tokenId,
            'exp' => $expire
        ]);

        return JWT::encode($data, self::$secretKey, JWT_ALGORITHM);
    }

    public static function validateToken($token, $secretKey)
    {
        try {
            return JWT::decode($token, new Key($secretKey, JWT_ALGORITHM));
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getSecretKey()
    {
        return self::$secretKey;
    }
}
?>

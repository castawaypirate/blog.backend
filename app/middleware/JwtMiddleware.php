<?php

require_once __DIR__ . '/../utils/jwt_helper.php';


class JWTMiddleware
{
    private $secretKey;

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function validateToken()
    {
        $authorizationHeader = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($authorizationHeader)) {
            // extract the token from the Authorization header (assuming it's in the 'Bearer <token>' format)
            $token = str_replace('Bearer ', '', $authorizationHeader);
            try {
                $decodedToken = JWTHelper::validateToken($token, $this->secretKey);
                if ($decodedToken === false) {
                    return ['success' => false, 'message' => 'Token validation failed.'];
                }
                return ['success' => true, 'user' => $decodedToken];
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Token validation failed.'];
            }
        } else {
            return ['success' => false, 'message' => 'Authorization header not found.'];
        }
    }

    private static function sendErrorResponse($message)
    {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
?>
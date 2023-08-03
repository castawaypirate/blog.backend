<?php

require_once __DIR__.'/../utils/jwt_helper.php';


class JWTMiddleware
{
    private $secretKey;

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function validateToken($request)
    {
        $authorizationHeader = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['Authorization'])) {
            $authorizationHeader = $_SERVER['Authorization'];
        }
    
        if (!empty($authorizationHeader)) {
            // Extract the token from the Authorization header (assuming it's in the "Bearer <token>" format)
            // You can further process the header to extract and validate the token as needed.
            $token = str_replace('Bearer ', '', $authorizationHeader);
            try {
                $decodedToken = JWTHelper::validateToken($token, $this->secretKey);
                // You can do additional validation or checks on the decoded token here if needed
                if ($decodedToken === false){
                    return ['success' => false, 'message' => "Token validation failed."];
                }
                return ['success' => true, 'user' => $decodedToken];
            } catch (Exception $e) {
                return ['success' => false, 'message' => "Token validation failed."];
            }
            // You can now use the $token variable for further processing or validation.
        } else {
            // Handle the case when the Authorization header is not present.
            return ['success' => false, 'message' => "Authorization header not found."];
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
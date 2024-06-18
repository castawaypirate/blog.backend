<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/jwt_helper.php';
require_once __DIR__.'/BaseController.php';

class UserController extends BaseController
{
    private $userModel;

    public function __construct($dbConnection)
    {
        $this->userModel = new User($dbConnection);
    }

    public function access($request)
    {
        if (!isset($request['username']) || !isset($request['password'])) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        $user = $this->userModel->getUserByUsername($request['username']);

        if ($user) {
            if (password_verify($request['password'], $user['password'])) {
                $token = JWTHelper::generateToken(['user_id' => $user['id'], 'username' => $user['username']]);
                if ($token) {
                    return ['success' => true, 'token' => $token, 'message' => 'Logged in.'];
                } else {
                    return ['success' => false, 'message' => 'Failed to generate JWT token.'];
                }
            }
            else {
                return ['success' => false, 'message' => 'Wrong password.'];
            }
        }

        $result = $this->userModel->addUser($request['username'], $request['email'] ?? null, $request['password']);

        if ($result['success']) {
            $user = $this->userModel->getUserByUsername($request['username']);
            $token = JWTHelper::generateToken(['user_id' => $user['id'], 'username' => $user['username']]);
            
            if ($token) {
                return ['success' => true, 'token' => $token, 'message' => 'User created successfully.'];
            } else {
                return ['success' => false, 'message' => 'User creation failed to generate token.'];
            }
        } else {
            return $result;
        }
    }

    public function validateUser()
    {
        $jwtMiddleware = new JWTMiddleware(JWTHelper::getSecretKey());
        $result = $jwtMiddleware->validateToken();
        return $result;
    }
}
?>
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
        // Validate required fields
        if (!isset($request['username']) || !isset($request['password'])) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        // Get a list of users with the particular username and see if the password matches any of them
        $usersList = $this->userModel->getUsersListByUsername($request['username']);

        if ($usersList) {
            foreach ($usersList as $existingUser) {
                if (password_verify($request['password'], $existingUser['password'])) {
                    $token = JWTHelper::generateToken(['user_id' => $existingUser['id'], 'username' => $existingUser['username']]);
                    if ($token) {
                        return ['success' => true, 'token' => $token, 'message' => 'Logged in.'];
                    } else {
                        return ['success' => false, 'message' => 'Failed to generate JWT token.'];
                    }
                }
            }
        }

        // If user with the particular pair of username, password doesn't exist make a new user
        $result = $this->userModel->addUser($request['username'], $request['email'] ?? null, $request['password']);

        if ($result['success']) {
            // Generate a JWT token
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
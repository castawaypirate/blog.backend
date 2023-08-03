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

    // Register a new user
    public function register($request)
    {
        // Validate required fields
        if (!isset($request['username']) || !isset($request['password'])) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        // Check if the username already exists
        $existingUser = $this->userModel->getUserByUsername($request['username']);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        // Optional: Validate other fields like email, etc.

        // Create the user in the database
        $result = $this->userModel->addUser($request['username'], $request['email'] ?? null, $request['password']);

        if ($result) {
            // Generate a JWT token
            $user = $this->userModel->getUserByUsername($request['username']);
            $token = JWTHelper::generateToken(['user_id' => $user['id'], 'username' => $user['username']]);
            
            if ($token) {
                return ['success' => true, 'token' => $token, 'message' => 'User registered successfully.'];
            } else {
                return ['success' => false, 'message' => 'User registration failed to generate token.'];
            }
        } else {
            return ['success' => false, 'message' => 'User registration failed.'];
        }
    }

    // User login and generate JWT token
    public function login($request)
    {
        // Validate required fields
        if (!isset($request['username']) || !isset($request['password'])) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        // Fetch the user from the database by username
        $user = $this->userModel->getUserByUsername($request['username']);

        // Check if the user exists and verify the password
        if (!$user || !password_verify($request['password'], $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Generate a JWT token
        $token = JWTHelper::generateToken(['user_id' => $user['id'], 'username' => $user['username']]);
        if ($token) {
            return ['success' => true, 'token' => $token];
        } else {
            return ['success' => false, 'message' => 'Failed to generate JWT token.'];
        }
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

        if ($result) {
            // Generate a JWT token
            $user = $this->userModel->getUserByUsername($request['username']);
            $token = JWTHelper::generateToken(['user_id' => $user['id'], 'username' => $user['username']]);
            
            if ($token) {
                return ['success' => true, 'token' => $token, 'message' => 'User created successfully.'];
            } else {
                return ['success' => false, 'message' => 'User creation failed to generate token.'];
            }
        } else {
            return ['success' => false, 'message' => 'User creation failed.'];
        }
    }

    public function validateUser($request)
    {
        $jwtMiddleware = new JWTMiddleware(JWTHelper::getSecretKey());
        $result = $jwtMiddleware->validateToken($request);
        return $result;
    }
    // You can implement logout and token refresh methods here (optional).
}
?>
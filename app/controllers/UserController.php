<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/jwt_helper.php';
require_once __DIR__.'/BaseController.php';

class UserController extends BaseController
{
    private $userModel;

    public function __construct($dbConnection) {
        $this->userModel = new User($dbConnection);
    }

    public function access($request) {
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

    public function validateUser() {
        $jwtMiddleware = new JWTMiddleware(JWTHelper::getSecretKey());
        $result = $jwtMiddleware->validateToken();
        return $result;
    }

    public function getUserData($userId) {
        $result = $this->userModel->getUserData($userId);
        return $result;
    }

    public function uploadProfilePic($userId) {
        if (empty($_FILES)) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        $fileCount= count($_FILES);

        if ($fileCount > 1) {
            return ['success' => false, 'message' => 'More than one file uploaded.'];
        }

        $uploadedFile = $_FILES['profilePic'];

        // pic must be 2MB max
        if ($uploadedFile['size'] > 2097152) {
            return ['success' => false, 'message' => 'File bigger than 2MB.'];

        }

        if ($uploadedFile['error'] !== 0) {
            return ['success' => false, 'message' => 'Error code: ' . $uploadedFile['error']];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($uploadedFile['tmp_name']);

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return ['success' => false, 'message' => 'Invalid file type (finfo). Only JPEG, PNG, and GIF are allowed.'];
        }

        if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
        }

        $result = $this->userModel->uploadProfilePic($userId, $uploadedFile);
        return $result;
    }

    public function getProfilePic($userId) {
        $result = $this->userModel->getProfilePic($userId);
        return $result;
    }

    public function deleteProfilePic($userId) {
        $result = $this->userModel->deleteProfilePic($userId);
        return $result;
    }

    public function changeUsername($userId, $request) {
        if (!isset($request['username'])) {
            return ['success' => false, 'message' => 'Username is required.'];
        }
        
        $result = $this->userModel->changeUsername($userId, $request['username']);
        return $result;
    }

    public function changePassword($userId, $request) {
        if (!isset($request['oldPassword'])) {
            return ['success' => false, 'message' => 'Old password is required.'];
        }

        if (!isset($request['newPassword'])) {
            return ['success' => false, 'message' => 'New password is required.'];
        }

        if ($request['newPassword'] === $request['oldPassword']) {
            return ['success' => false, 'message' => 'New password cannot be the same as the old password.'];
        }

        $user = $this->userModel->getUserByUserId($userId);

        if ($user) {
            if (password_verify($request['oldPassword'], $user['password'])) {
                $result = $this->userModel->changePassword($userId, $request['newPassword']);
                return $result;
            }
            else {
                return ['success' => false, 'message' => 'Wrong password.'];
            }
        } else {
            return ['success' => false, 'message' => 'User not found.'];
        }
    }

    public function deletePost($userId) {
        $result = $this->userModel->deleteUser($userId);
        return $result;
    }
}
?>
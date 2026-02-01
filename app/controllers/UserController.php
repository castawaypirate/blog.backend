<?php
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/BaseController.php';

class UserController extends BaseController
{
    private $userService;

    public function __construct($userService)
    {
        $this->userService = $userService;
    }

    public function access($request)
    {
        if (!isset($request['username']) || !isset($request['password'])) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        return $this->userService->authenticateOrRegister($request['username'], $request['password'], $request['email'] ?? null);
    }

    public function validateUser()
    {
        // need to make sure JWTMiddleware is available.
        require_once __DIR__ . '/../middleware/JwtMiddleware.php';
        $jwtMiddleware = new JWTMiddleware(JWTHelper::getSecretKey());
        $result = $jwtMiddleware->validateToken();
        return $result;
    }

    public function getUserData($userId)
    {
        return $this->userService->getUserData($userId);
    }

    public function uploadProfilePic($userId)
    {
        if (empty($_FILES)) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        $fileCount = count($_FILES);

        if ($fileCount > 1) {
            return ['success' => false, 'message' => 'More than one file uploaded.'];
        }

        $uploadedFile = $_FILES['profilePic'];

        // pic must be 2MB max
        if ($uploadedFile['size'] > 10000000) {
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

        return $this->userService->uploadProfilePic($userId, $uploadedFile);
    }

    public function getProfilePic($userId)
    {
        return $this->userService->getProfilePic($userId);
    }

    public function deleteProfilePic($userId)
    {
        return $this->userService->deleteProfilePic($userId);
    }

    public function changeUsername($userId, $request)
    {
        if (!isset($request['username'])) {
            return ['success' => false, 'message' => 'Username is required.'];
        }

        return $this->userService->changeUsername($userId, $request['username']);
    }

    public function changePassword($userId, $request)
    {
        if (!isset($request['oldPassword'])) {
            return ['success' => false, 'message' => 'Old password is required.'];
        }

        if (!isset($request['newPassword'])) {
            return ['success' => false, 'message' => 'New password is required.'];
        }

        if ($request['newPassword'] === $request['oldPassword']) {
            return ['success' => false, 'message' => 'New password cannot be the same as the old password.'];
        }

        return $this->userService->changePassword($userId, $request['oldPassword'], $request['newPassword']);
    }

    public function deleteUser($userId)
    {
        return $this->userService->deleteUser($userId);
    }

    public function searchUsers($userId)
    {
        if (!isset($_GET['query'])) {
            return ['success' => false, 'message' => 'Query parameter is required.'];
        }
        $query = $_GET['query'];
        return $this->userService->searchUsers($query, $userId);
    }
}
?>
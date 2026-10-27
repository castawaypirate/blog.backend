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
        if (!is_string($request['username']) || !is_string($request['password'])) {
            return ['success' => false, 'message' => 'Username and password must be text.'];
        }
        if (isset($request['email']) && !is_string($request['email'])) {
            return ['success' => false, 'message' => 'Email must be text.'];
        }

        return $this->userService->authenticateOrRegister($request['username'], $request['password'], $this->getClientIp(), $request['email'] ?? null);
    }

    // The API only gets requests through local Apache proxies, so REMOTE_ADDR is loopback.
    // Each proxy appends the address it saw to X-Forwarded-For, so the real client is the
    // right-most entry that isn't loopback. Entries left of it come from the client and can be faked.
    private function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($remoteAddr, ['127.0.0.1', '::1'], true) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $remoteAddr;
        }

        $forwarded = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
        foreach ($forwarded as $ip) {
            if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
                return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $remoteAddr;
            }
        }
        return $remoteAddr;
    }

    public function validateUser()
    {
        // need to make sure JWTMiddleware is available.
        require_once __DIR__ . '/../middleware/JwtMiddleware.php';
        $jwtMiddleware = new JWTMiddleware(JWTHelper::getSecretKey());
        $result = $jwtMiddleware->validateToken();

        // a valid signature isn't enough: the account must still exist and not be marked for deletion
        if ($result['success'] && !$this->userService->isActiveUser((int) $result['user']->user_id)) {
            return ['success' => false, 'message' => 'Token validation failed.'];
        }
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

        return $this->userService->uploadProfilePic($userId, $uploadedFile, $mimeType);
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
        if (!is_string($request['username'])) {
            return ['success' => false, 'message' => 'Username must be text.'];
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

        if (!is_string($request['oldPassword']) || !is_string($request['newPassword'])) {
            return ['success' => false, 'message' => 'Passwords must be text.'];
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
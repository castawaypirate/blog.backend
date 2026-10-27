<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/LoginAttemptRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/jwt_helper.php';

class UserService
{
    // failed logins allowed per account and per IP inside the window before access is refused
    private const MAX_FAILED_LOGINS = 10;
    private const FAILED_LOGIN_WINDOW = 900;

    private $userRepository;
    private $messageRepository;
    private $loginAttemptRepository;
    private $userDeletionDelay;

    public function __construct(UserRepository $userRepository, MessageRepository $messageRepository, LoginAttemptRepository $loginAttemptRepository)
    {
        $this->userRepository = $userRepository;
        $this->messageRepository = $messageRepository;
        $this->loginAttemptRepository = $loginAttemptRepository;
        $this->userDeletionDelay = defined('USER_DELETION_DELAY') ? USER_DELETION_DELAY : 7200;
    }

    public function authenticateOrRegister(string $username, string $password, string $ip, ?string $email = null): array
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user) {
            if ($this->loginAttemptRepository->countRecentFailuresForUser($user->getId(), self::FAILED_LOGIN_WINDOW) >= self::MAX_FAILED_LOGINS
                || $this->loginAttemptRepository->countRecentFailuresForIp($ip, self::FAILED_LOGIN_WINDOW) >= self::MAX_FAILED_LOGINS) {
                return ['success' => false, 'message' => 'Too many attempts. Try again later.'];
            }

            if (password_verify($password, $user->getPassword())) {
                $this->userRepository->updateLastLogin($user->getId());
                $this->userRepository->undoDeletion($user->getId());

                $token = JWTHelper::generateToken(['user_id' => $user->getId(), 'username' => $user->getUsername()]);
                if ($token) {
                    return ['success' => true, 'token' => $token, 'message' => 'Logged in.'];
                }
                return ['success' => false, 'message' => 'Failed to generate JWT token.'];
            } else {
                $this->loginAttemptRepository->recordFailure($user->getId(), $ip);
                return ['success' => false, 'message' => 'Wrong password.'];
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $newUser = new User(null, $username, $email, $hashedPassword);

        if ($this->userRepository->create($newUser)) {
            $user = $this->userRepository->findByUsername($username);
            $token = JWTHelper::generateToken(['user_id' => $user->getId(), 'username' => $user->getUsername()]);

            if ($token) {
                return ['success' => true, 'token' => $token, 'message' => 'User created successfully.'];
            }
            return ['success' => false, 'message' => 'User created but failed to generate token.'];
        }

        return ['success' => false, 'message' => 'Error adding the user.'];
    }

    public function validateUser(): array
    {
        return [];
    }

    public function isActiveUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        return $user !== null && $user->getDeletedAt() === null;
    }

    public function getUserData(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user) {
            $hasSentMessages = $this->messageRepository->hasSentMessages($userId);
            return [
                'success' => true,
                'user' => [
                    'username' => $user->getUsername(),
                    'has_sent_messages' => $hasSentMessages
                ]
            ];
        }
        return ['success' => false, 'message' => 'User not found.'];
    }

    public function uploadProfilePic(int $userId, array $file, string $mimeType): array
    {
        $uploads = defined('ROOT_DIR') ? ROOT_DIR . '/uploads/' : __DIR__ . '/../../uploads/';

        // the stored name never comes from the client: extension from the detected type, random basename
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        if (!isset($extensions[$mimeType])) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to select user\'s data.'];
        }

        $currentPath = $user->getProfilePicPath();

        // kept only as data; it is never used to build a path
        $originalName = mb_substr(basename($file['name']), 0, 255);

        $userDirectory = $uploads . $userId;
        if (!is_dir($userDirectory)) {
            if (!mkdir($userDirectory, 0777, true)) {
                return ['success' => false, 'message' => 'Failed to create user directory.'];
            }
        }

        $tempBackupPath = null;
        $fullCurrentPath = $currentPath ? $uploads . $currentPath : null;

        if ($fullCurrentPath && file_exists($fullCurrentPath)) {
            $tempBackupPath = $fullCurrentPath . '.bak';
            if (!rename($fullCurrentPath, $tempBackupPath)) {
                return ['success' => false, 'message' => 'Old to new process failure.'];
            }
        }

        $shortPath = $userId . '/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        $destination = $uploads . $shortPath;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            if ($tempBackupPath) {
                rename($tempBackupPath, $fullCurrentPath);
            }
            return ['success' => false, 'message' => 'Can\'t move uploaded file.'];
        }

        if ($this->userRepository->updateProfilePic($userId, $shortPath, $mimeType, $originalName)) {
            if ($tempBackupPath) {
                unlink($tempBackupPath);
            }
            return ['success' => true, 'message' => 'File is uploaded.'];
        } else {
            unlink($destination);
            if ($tempBackupPath) {
                rename($tempBackupPath, $fullCurrentPath);
            }
            return ['success' => false, 'message' => 'Query execution failure.'];
        }
    }

    public function getProfilePic(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $path = $user->getProfilePicPath();
        if (!$path) {
            return ['success' => false, 'message' => 'Profile picture path is null.'];
        }

        $uploads = defined('ROOT_DIR') ? ROOT_DIR . '/uploads/' : __DIR__ . '/../../uploads/';
        $fullPath = $uploads . $path;

        if (!file_exists($fullPath)) {
            return ['success' => false, 'message' => 'Profile picture file does not exist.'];
        }

        return [
            'success' => true,
            'data' => [
                'profile_pic_path' => $path,
                'profile_pic_mime_type' => $user->getProfilePicMimeType(),
                'profile_pic_full_path' => $fullPath
            ]
        ];
    }

    public function deleteProfilePic(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $path = $user->getProfilePicPath();
        if (!$path) {
            return ['success' => false, 'message' => 'Profile picture does not exist.'];
        }

        $uploads = defined('ROOT_DIR') ? ROOT_DIR . '/uploads/' : __DIR__ . '/../../uploads/';
        $fullPath = $uploads . $path;

        if ($this->userRepository->removeProfilePic($userId)) {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            return ['success' => true, 'message' => 'Profile picture deleted successfully.'];
        }
        return ['success' => false, 'message' => 'Failed to update database.'];
    }

    public function changeUsername(int $userId, string $newUsername): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User doesn\'t exist. Weird.'];
        }

        if ($user->getUsername() === $newUsername) {
            return ['success' => false, 'message' => 'Wait a minute. That\'s the same username.'];
        }

        if ($this->userRepository->usernameExists($newUsername)) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        if ($this->userRepository->updateUsername($userId, $newUsername)) {
            return ['success' => true, 'message' => 'Username updated successfully.'];
        }
        return ['success' => false, 'message' => 'Error updating the username.'];
    }

    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if (!password_verify($oldPassword, $user->getPassword())) {
            return ['success' => false, 'message' => 'Wrong password.'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($this->userRepository->updatePassword($userId, $hashedPassword)) {
            return ['success' => true, 'message' => 'Password updated successfully.'];
        }
        return ['success' => false, 'message' => 'Error updating the password.'];
    }

    public function deleteUser(int $userId): array
    {
        if ($this->userRepository->markAsDeleted($userId, (int) $this->userDeletionDelay)) {
            return ['success' => true, 'message' => 'User marked as deleted successfully.'];
        }
        return ['success' => false, 'message' => 'Failed to mark user as deleted.'];
    }

    public function searchUsers(string $query, int $currentUserId): array
    {
        $users = $this->userRepository->searchUsers($query, $currentUserId);
        return ['success' => true, 'users' => $users];
    }
}

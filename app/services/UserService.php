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
    private $fileMover;
    private $uploadsDir;

    public function __construct(UserRepository $userRepository, MessageRepository $messageRepository, LoginAttemptRepository $loginAttemptRepository, ?callable $fileMover = null, ?string $uploadsDir = null)
    {
        // tests inject a mover because move_uploaded_file only accepts real HTTP uploads
        $this->fileMover = $fileMover ?? 'move_uploaded_file';
        $this->uploadsDir = $uploadsDir ?? (defined('ROOT_DIR') ? ROOT_DIR . '/uploads/' : __DIR__ . '/../../uploads/');
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
        $uploads = $this->uploadsDir;

        // the stored name never comes from the client: extension from the detected type, random basename
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($extensions[$mimeType])) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, WebP, and GIF are allowed.'];
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
            if (!mkdir($userDirectory, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create user directory.'];
            }
        }

        $tempBackupPath = null;
        // only files we named ourselves, inside this user's own folder, are ever touched
        $fullCurrentPath = $this->ownedPicturePath($uploads, $userId, $currentPath);

        if ($fullCurrentPath) {
            $tempBackupPath = $fullCurrentPath . '.bak';
            if (!rename($fullCurrentPath, $tempBackupPath)) {
                return ['success' => false, 'message' => 'Old to new process failure.'];
            }
        }

        $shortPath = $userId . '/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        $destination = $uploads . $shortPath;

        if (!($this->fileMover)($file['tmp_name'], $destination)) {
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

    /**
     * Full path of the user's stored picture, or null if it is missing or is not
     * <userId>/<32 hex>.<ext> resolving inside the uploads directory (e.g. legacy names).
     */
    private function ownedPicturePath(string $uploads, int $userId, ?string $path): ?string
    {
        if (!$path || !preg_match('#^' . $userId . '/[a-f0-9]{32}\.(jpg|png|gif|webp)$#', $path)) {
            return null;
        }

        $root = realpath($uploads);
        $full = realpath($uploads . $path);
        if ($root === false || $full === false || strpos($full, $root . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $full;
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

        $uploads = $this->uploadsDir;
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

        $uploads = $this->uploadsDir;
        $fullPath = $this->ownedPicturePath($uploads, $userId, $path);

        if ($this->userRepository->removeProfilePic($userId)) {
            if ($fullPath) {
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

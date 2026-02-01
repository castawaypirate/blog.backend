<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/jwt_helper.php';

class UserService
{
    private $userRepository;
    private $messageRepository;
    private $userDeletionDelay;

    public function __construct(UserRepository $userRepository, MessageRepository $messageRepository)
    {
        $this->userRepository = $userRepository;
        $this->messageRepository = $messageRepository;
        $this->userDeletionDelay = defined('USER_DELETION_DELAY') ? USER_DELETION_DELAY : 7200;
    }

    public function authenticateOrRegister(string $username, string $password, ?string $email = null): array
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user) {
            if (password_verify($password, $user->getPassword())) {
                $this->userRepository->updateLastLogin($user->getId());
                $this->userRepository->undoDeletion($user->getId());

                $token = JWTHelper::generateToken(['user_id' => $user->getId(), 'username' => $user->getUsername()]);
                if ($token) {
                    return ['success' => true, 'token' => $token, 'message' => 'Logged in.'];
                }
                return ['success' => false, 'message' => 'Failed to generate JWT token.'];
            } else {
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

    public function uploadProfilePic(int $userId, array $file): array
    {
        $uploads = defined('ROOT_DIR') ? ROOT_DIR . '/uploads/' : __DIR__ . '/../../uploads/';

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to select user\'s data.'];
        }

        $username = $user->getUsername();
        $currentPath = $user->getProfilePicPath();

        $newProfilePicExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

        $userDirectory = $uploads . $username;
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

        $shortPath = $username . '/' . $file['name'];
        $destination = $uploads . $shortPath;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            if ($tempBackupPath) {
                rename($tempBackupPath, $fullCurrentPath);
            }
            return ['success' => false, 'message' => 'Can\'t move uploaded file.'];
        }

        if ($this->userRepository->updateProfilePic($userId, $shortPath, $file['type'])) {
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
        $currentTimestamp = new DateTime();
        $deletionTimestamp = $currentTimestamp->add(new DateInterval('PT' . $this->userDeletionDelay . 'S'))->format('Y-m-d H:i:s');

        if ($this->userRepository->markAsDeleted($userId, $deletionTimestamp)) {
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

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
        // Hardcoded dependency from config constant, could be injected but fine for now
        $this->userDeletionDelay = defined('USER_DELETION_DELAY') ? USER_DELETION_DELAY : 7200;
    }

    public function authenticateOrRegister(string $username, string $password, ?string $email = null): array
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user) {
            // Login Flow
            if (password_verify($password, $user->getPassword())) {
                // Update login stats
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

        // Registration Flow
        // Note: Password hashing happens here before creating the entity or inside entity/repo?
        // Old code hashed in `addUser`.
        // We should hash here or in the repo. The plan says "pure entity", so maybe entity stores hashed password?
        // Let's hash here.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // ID is null initially
        $newUser = new User(null, $username, $email, $hashedPassword);

        if ($this->userRepository->create($newUser)) {
            // Retrieve formatted user to get ID
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
        // Re-using the middleware logic invoked in controller currently.
        // Actually, the controller instantiated JWTMiddleware.
        // It might be better to keep that in Controller or move check here.
        // For strict service pattern, Controller should call Service to get domain objects.
        // But `validateUser` in Controller calls `JWTMiddleware`.
        // Let's replicate what the Controller did:
        // $jwtMiddleware = new JWTMiddleware(JWTHelper::getSecretKey());
        // $result = $jwtMiddleware->validateToken();
        // Since JWTMiddleware is arguably a cross-cutting concern, calling it here or in Controller is debatable.
        // But the previous `UserController::validateUser` was just a wrapper.
        // We will leave the middleware usage in the controller or assume the controller passed the validated user ID.
        // Wait, the previous controller called `$this->userModel` methods AFTER verifying token.
        // The `validateUser` method in `UserController` was just a helper to call the middleware.
        // We can leave `validateUser` in the Controller or a base helper.
        // I will skipping implementing `validateUser` here as it belongs to Middleware/Controller layer.
        return [];
    }

    public function getUserData(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user) {
            $hasSentMessages = $this->messageRepository->hasSentMessages($userId);
            // mimic old response structure: ['success'=>true, 'user'=> ['username'=>...]]
            // Old `getUserData` only returned username?
            // Checking old code: `SELECT username FROM Users...`
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
        // Logic from User::uploadProfilePic
        // Requires transaction usually, but here handled by rollback logic.
        $uploads = defined('ROOT_DIR') ? ROOT_DIR . '/uploads/' : __DIR__ . '/../../uploads/';

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to select user\'s data.'];
        }

        $username = $user->getUsername();
        $currentPath = $user->getProfilePicPath();

        $newProfilePicExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        // Naming strategy: username/filename

        // Create directory
        $userDirectory = $uploads . $username;
        if (!is_dir($userDirectory)) {
            if (!mkdir($userDirectory, 0777, true)) {
                return ['success' => false, 'message' => 'Failed to create user directory.'];
            }
        }

        // Backup existing logic (simulated from old code)
        // Old code renamed existing pic to '...1.ext' to backup.
        // Simplified Logic: Just overwrite? Old code did complex rename/rollback.
        // I'll try to replicate the safety logic.

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
            // Restore backup
            if ($tempBackupPath) {
                rename($tempBackupPath, $fullCurrentPath);
            }
            return ['success' => false, 'message' => 'Can\'t move uploaded file.'];
        }

        // DB Update
        if ($this->userRepository->updateProfilePic($userId, $shortPath, $file['type'])) {
            // Success, remove backup
            if ($tempBackupPath) {
                unlink($tempBackupPath);
            }
            return ['success' => true, 'message' => 'File is uploaded.'];
        } else {
            // DB Failed, rollback file
            unlink($destination); // delete new file
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

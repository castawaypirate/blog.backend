<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/MazeChallengeRepository.php';
require_once __DIR__ . '/../models/Message.php';

use OpenPGP\OpenPGP;

class MazeService
{
    private $userRepository;
    private $messageRepository;
    private $mazeChallengeRepository;
    private $adminUsername;
    private $privateKeyPath;
    private $passphrase;
    private $bonusMessage;

    public function __construct(
        UserRepository $userRepository,
        MessageRepository $messageRepository,
        MazeChallengeRepository $mazeChallengeRepository
    ) {
        $this->userRepository = $userRepository;
        $this->messageRepository = $messageRepository;
        $this->mazeChallengeRepository = $mazeChallengeRepository;
        
        $this->adminUsername = defined('MAZE_ADMIN_USERNAME') ? MAZE_ADMIN_USERNAME : 'admin';
        $this->privateKeyPath = defined('MAZE_PRIVATE_KEY_PATH') ? MAZE_PRIVATE_KEY_PATH : '';
        $this->passphrase = defined('MAZE_PASSPHRASE') ? MAZE_PASSPHRASE : '';
        $this->bonusMessage = defined('MAZE_BONUS_MESSAGE') ? MAZE_BONUS_MESSAGE : 'random';
    }

    public function findPendingChallenges()
    {
        // Find messages TO the admin that look like PGP messages or public keys
        // This is a bit complex. The implementation plan suggests:
        // 1. Find the admin user ID
        $admin = $this->userRepository->findByUsername($this->adminUsername);
        if (!$admin) {
            return [];
        }
        $adminId = $admin->getId();

        // 2. Find messages to admin that are not processed yet
        // We'll use a query to find all senders who sent messages to admin
        // and check if we have a pending challenge for them.
        
        // Actually, let's simplify: the cron script will do the grouping.
        // This method should just return messages to admin.
        return $this->messageRepository->getRecentConversations($adminId);
    }

    public function processChallenge($userId, $encryptedUsername, $userPublicKeyBlock)
    {
        try {
            // 1. Decrypt the username
            $decryptedUsername = $this->decryptMessage($encryptedUsername);
            if (!$decryptedUsername) {
                return $this->failChallenge($userId, 'Could not decrypt username');
            }

            $cleanedUsername = trim($decryptedUsername);

            // 2. Validate user exists and matches the sender
            $targetUser = $this->userRepository->findByUsername($cleanedUsername);
            if (!$targetUser || $targetUser->getId() != $userId) {
                $this->sendPlaintextReply($userId, 'try again');
                $errorMsg = !$targetUser ? "Username does not exist: $cleanedUsername" : "Identity mismatch: PGP claims $cleanedUsername but sender ID is $userId";
                return $this->failChallenge($userId, $errorMsg);
            }

            // 3. Encrypt bonus message with user's public key
            $encryptedResponse = $this->encryptResponse($this->bonusMessage, $userPublicKeyBlock);
            if (!$encryptedResponse) {
                return $this->failChallenge($userId, 'Could not encrypt response');
            }

            // 4. Send encrypted reply
            $admin = $this->userRepository->findByUsername($this->adminUsername);
            $this->sendReply($admin->getId(), $userId, $encryptedResponse);

            // 5. Store public key and mark as processed
            $this->mazeChallengeRepository->create($userId, $userPublicKeyBlock, null, null);
            $this->mazeChallengeRepository->updateStatus($this->mazeChallengeRepository->findByUserId($userId)['id'], 'replied', $cleanedUsername);

            return true;
        } catch (Exception $e) {
            error_log('MazeService Error: ' . $e->getMessage());
            return false;
        }
    }

    public function decryptMessage($encryptedText)
    {
        if (!file_exists($this->privateKeyPath)) {
            error_log('Private key file not found at: ' . $this->privateKeyPath);
            return null;
        }

        $privateKeyArmored = file_get_contents($this->privateKeyPath);
        
        try {
            $privateKey = OpenPGP::decryptPrivateKey($privateKeyArmored, $this->passphrase);
            $decryptedMessage = OpenPGP::decryptMessage($encryptedText, [$privateKey]);
            return $decryptedMessage->getLiteralData()->getData();
        } catch (Exception $e) {
            error_log('PGP Decryption failed: ' . $e->getMessage());
            return null;
        }
    }

    public function encryptResponse($plaintext, $userPublicKeyArmored)
    {
        try {
            $publicKey = OpenPGP::readPublicKey($userPublicKeyArmored);
            $literalMessage = OpenPGP::createLiteralMessage($plaintext);
            $encryptedMessage = OpenPGP::encrypt($literalMessage, [$publicKey]);
            return (string) $encryptedMessage;
        } catch (Exception $e) {
            error_log('PGP Encryption failed: ' . $e->getMessage());
            return null;
        }
    }

    private function sendReply($senderId, $receiverId, $content)
    {
        $message = new Message($senderId, $receiverId, $content);
        return $this->messageRepository->create($message);
    }

    private function sendPlaintextReply($userId, $text)
    {
        $admin = $this->userRepository->findByUsername($this->adminUsername);
        if ($admin) {
            return $this->sendReply($admin->getId(), $userId, $text);
        }
        return false;
    }

    private function failChallenge($userId, $error)
    {
        error_log('Maze Challenge Failed for user ' . $userId . ': ' . $error);
        // We might want to record this in the DB too
        return false;
    }
}

<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/MazeChallengeRepository.php';
require_once __DIR__ . '/../repositories/MazeProgressRepository.php';
require_once __DIR__ . '/MazeService.php';

/**
 * Extracted from maze_processor.php cron script.
 * Encapsulates the per-user message scanning, batching, override detection,
 * persistent memory, and routing logic for the maze challenge flow.
 */
class MazeProcessor
{
    private PDO $db;
    private UserRepository $userRepository;
    private MessageRepository $messageRepository;
    private MazeChallengeRepository $mazeChallengeRepository;
    private MazeProgressRepository $mazeProgressRepository;
    private MazeService $mazeService;

    public function __construct(
        PDO $db,
        UserRepository $userRepository,
        MessageRepository $messageRepository,
        MazeChallengeRepository $mazeChallengeRepository,
        MazeProgressRepository $mazeProgressRepository,
        MazeService $mazeService
    ) {
        $this->db = $db;
        $this->userRepository = $userRepository;
        $this->messageRepository = $messageRepository;
        $this->mazeChallengeRepository = $mazeChallengeRepository;
        $this->mazeProgressRepository = $mazeProgressRepository;
        $this->mazeService = $mazeService;
    }

    /**
     * Process all users who have sent messages to admin.
     * Returns an array of per-user result strings for logging.
     */
    public function processAll(int $adminId): array
    {
        $query = "SELECT DISTINCT sender_id FROM Messages WHERE receiver_id = :admin_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':admin_id', $adminId);
        $stmt->execute();
        $senders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($senders as $sender) {
            $userId = $sender['sender_id'];
            $results[$userId] = $this->processUser($userId, $adminId);
        }
        return $results;
    }

    /**
     * Process a single user's messages to admin.
     * This is the core method extracted from the maze_processor.php foreach loop.
     *
     * @return string Action taken: 'already_replied', 'no_messages', 'no_maze_content',
     *                'processChallenge', 'sendMissingKeyHint', 'sendMissingMessageHint', 'skipped_duplicate'
     */
    public function processUser(int $userId, int $adminId): string
    {
        // 1. Skip if already successfully replied
        $existing = $this->mazeChallengeRepository->findByUserId($userId);
        if ($existing && $existing['status'] === 'replied') {
            return 'already_replied';
        }

        // 2. Get last processed message ID
        $lastId = $this->mazeProgressRepository->getLastProcessedId($userId);

        // 3. Fetch conversation with admin since lastId
        $query = "SELECT * FROM Messages 
                  WHERE ((sender_id = :u1 AND receiver_id = :a1) 
                     OR (sender_id = :a2 AND receiver_id = :u2))
                  AND id > :last_id 
                  ORDER BY id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':u1', $userId);
        $stmt->bindParam(':a1', $adminId);
        $stmt->bindParam(':a2', $adminId);
        $stmt->bindParam(':u2', $userId);
        $stmt->bindParam(':last_id', $lastId);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return 'no_messages';
        }

        // 4. Scan for CT/PK markers — last wins
        $encryptedUsername = null;
        $publicKey = null;
        $encryptedMsgId = null;
        $publicKeyMsgId = null;
        $maxIdInBatch = $lastId;
        $isNewMsgInChat = false;
        $isNewKeyInChat = false;

        foreach ($rows as $row) {
            $maxIdInBatch = max($maxIdInBatch, (int)$row['id']);
            if ($row['sender_id'] != $userId) continue;

            $content = $row['content'];
            
            if (strpos($content, '-----BEGIN CIPHERTEXT-----') !== false) {
                $encryptedUsername = $content;
                $encryptedMsgId = $row['id'];
                $isNewMsgInChat = true;
            }
            
            if (strpos($content, '-----BEGIN PUBLIC KEY-----') !== false) {
                $publicKey = $content;
                $publicKeyMsgId = $row['id'];
                $isNewKeyInChat = true;
            }
        }

        // 5. Wake-up check: only proceed if new maze content was found
        if (!$isNewMsgInChat && !$isNewKeyInChat) {
            $this->mazeProgressRepository->setLastProcessedId($userId, $maxIdInBatch);
            return 'no_maze_content';
        }

        // 6. Override & deletion detection
        $isMsgOverride = ($isNewMsgInChat && $existing && $existing['encrypted_username_msg_id'] && $existing['encrypted_username_msg_id'] != $encryptedMsgId);
        $isKeyOverride = ($isNewKeyInChat && $existing && $existing['public_key_msg_id'] !== null && $existing['public_key_msg_id'] != $publicKeyMsgId);
        $isMsgDeleted = ($isNewMsgInChat && $existing && $existing['encrypted_username_msg_id'] === null);
        $isKeyDeleted = ($isNewKeyInChat && $existing && $existing['public_key_msg_id'] === null);

        // 7. Persistent memory: check DB for missing parts
        if (!$encryptedUsername || !$publicKey) {
            if ($existing) {
                if (!$encryptedUsername && $existing['encrypted_username_msg_id']) {
                    $oldMsg = $this->messageRepository->getById($existing['encrypted_username_msg_id']);
                    if ($oldMsg) {
                        $encryptedUsername = $oldMsg->getContent();
                        $encryptedMsgId = $oldMsg->getId();
                    }
                }
                if (!$publicKey && $existing['public_key'] && $existing['public_key_msg_id'] !== null) {
                    $oldKeyMsg = $this->messageRepository->getById($existing['public_key_msg_id']);
                    if ($oldKeyMsg) {
                        $publicKey = $existing['public_key'];
                        $publicKeyMsgId = $existing['public_key_msg_id'];
                    }
                }
            }
        }

        // 8. Route to appropriate service method
        $action = '';
        if ($encryptedUsername && $publicKey) {
            $this->mazeService->processChallenge($userId, $encryptedUsername, $publicKey, $encryptedMsgId, $publicKeyMsgId, $isMsgOverride, $isKeyOverride);
            $action = 'processChallenge';
        } elseif ($encryptedUsername) {
            $decrypted = $this->mazeService->decryptMessage($encryptedUsername);
            $identityMatch = false;
            if ($decrypted) {
                $cleaned = trim($decrypted);
                $targetUser = $this->userRepository->findByUsername($cleaned);
                $identityMatch = ($targetUser && $targetUser->getId() == $userId);
            }
            
            if (!$existing || $existing['status'] !== 'pending_key' || $isMsgOverride || $isMsgDeleted) {
                $this->mazeService->sendMissingKeyHint($userId, $encryptedMsgId, $isMsgOverride, $identityMatch, !!$decrypted);
                $action = 'sendMissingKeyHint';
            } else {
                $action = 'skipped_duplicate';
            }
        } elseif ($publicKey) {
            if (!$existing || $existing['status'] !== 'pending_message' || $isKeyOverride || $isKeyDeleted) {
                $this->mazeService->sendMissingMessageHint($userId, $publicKey, $publicKeyMsgId, $isKeyOverride);
                $action = 'sendMissingMessageHint';
            } else {
                $action = 'skipped_duplicate';
            }
        }

        // 9. Update progress
        $this->mazeProgressRepository->setLastProcessedId($userId, $maxIdInBatch);

        return $action;
    }
}

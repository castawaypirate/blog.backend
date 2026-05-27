<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/MazeChallengeRepository.php';
require_once __DIR__ . '/../repositories/MazeProgressRepository.php';
require_once __DIR__ . '/../services/MazeService.php';

// 1. Lock Mechanism
$lockFile = __DIR__ . '/maze.lock';
if (file_exists($lockFile)) {
    $pid = file_get_contents($lockFile);
    // Check if process is actually running (linux specific)
    if (file_exists("/proc/$pid")) {
        die("Processor already running (PID: $pid). Skipping this cycle.\n");
    }
    // If not running, stale lock, delete it
    unlink($lockFile);
}

file_put_contents($lockFile, getmypid());

// Ensure lock is removed on exit
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

// Initialize DB and Repositories
$database = new Database();
$db = $database->getConnection();

$userRepository = new UserRepository($db);
$messageRepository = new MessageRepository($db);
$mazeChallengeRepository = new MazeChallengeRepository($db);
$mazeProgressRepository = new MazeProgressRepository($db);
$mazeService = new MazeService($userRepository, $messageRepository, $mazeChallengeRepository);

echo "Starting Maze Processor...\n";

// 2. Find Admin
$adminUsername = defined('MAZE_ADMIN_USERNAME') ? MAZE_ADMIN_USERNAME : 'admin';
$admin = $userRepository->findByUsername($adminUsername);
if (!$admin) {
    die("Error: Admin user '$adminUsername' not found.\n");
}
$adminId = $admin->getId();

// 3. Find unique senders who messaged admin since last run
$query = "SELECT DISTINCT sender_id FROM Messages WHERE receiver_id = :admin_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':admin_id', $adminId);
$stmt->execute();
$senders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($senders as $sender) {
    $userId = $sender['sender_id'];
    
    // Skip if already successfully replied
    $existing = $mazeChallengeRepository->findByUserId($userId);
    if ($existing && $existing['status'] === 'replied') {
        continue;
    }

    $lastId = $mazeProgressRepository->getLastProcessedId($userId);
    
    // Fetch conversation with admin since lastId
    $query = "SELECT * FROM Messages 
              WHERE ((sender_id = :u1 AND receiver_id = :a1) 
                 OR (sender_id = :a2 AND receiver_id = :u2))
              AND id > :last_id 
              ORDER BY id ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':u1', $userId);
    $stmt->bindParam(':a1', $adminId);
    $stmt->bindParam(':a2', $adminId);
    $stmt->bindParam(':u2', $userId);
    $stmt->bindParam(':last_id', $lastId);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        continue;
    }

    echo "Checking new messages from user ID $userId (since ID $lastId)...\n";

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

    // 4. Wake Up Logic: Only "think" about the maze if we found new maze content in this batch
    if (!$isNewMsgInChat && !$isNewKeyInChat) {
        $mazeProgressRepository->setLastProcessedId($userId, $maxIdInBatch);
        continue;
    }

    $isMsgOverride = ($isNewMsgInChat && $existing && $existing['encrypted_username_msg_id'] && $existing['encrypted_username_msg_id'] != $encryptedMsgId);
    $isKeyOverride = ($isNewKeyInChat && $existing && $existing['public_key_msg_id'] !== null && $existing['public_key_msg_id'] != $publicKeyMsgId);
    $isMsgDeleted = ($isNewMsgInChat && $existing && $existing['encrypted_username_msg_id'] === null);
    $isKeyDeleted = ($isNewKeyInChat && $existing && $existing['public_key_msg_id'] === null);

    // 5. Persistent Memory: Check DB for missing parts if needed
    if (!$encryptedUsername || !$publicKey) {
        if ($existing) {
            if (!$encryptedUsername && $existing['encrypted_username_msg_id']) {
                $oldMsg = $messageRepository->getById($existing['encrypted_username_msg_id']);
                if ($oldMsg) {
                    $encryptedUsername = $oldMsg->getContent();
                    $encryptedMsgId = $oldMsg->getId();
                    echo "Reusing PGP message from previous attempt (ID $encryptedMsgId).\n";
                }
            }
            if (!$publicKey && $existing['public_key'] && $existing['public_key_msg_id'] !== null) {
                $oldKeyMsg = $messageRepository->getById($existing['public_key_msg_id']);
                if ($oldKeyMsg) {
                    $publicKey = $existing['public_key'];
                    $publicKeyMsgId = $existing['public_key_msg_id'];
                    echo "Reusing Public Key from previous attempt.\n";
                }
            }
        }
    }

    // 6. Handle logic
    if ($encryptedUsername && $publicKey) {
        echo "Found complete challenge for user $userId. Processing...\n";
        $mazeService->processChallenge($userId, $encryptedUsername, $publicKey, $encryptedMsgId, $publicKeyMsgId, $isMsgOverride, $isKeyOverride);
    } elseif ($encryptedUsername) {
        echo "Found message but no key for user $userId. Checking identity and hinting...\n";
        // New: Check identity even if key is missing
        $decrypted = $mazeService->decryptMessage($encryptedUsername);
        $identityMatch = false;
        if ($decrypted) {
            $cleaned = trim($decrypted);
            $targetUser = $userRepository->findByUsername($cleaned);
            $identityMatch = ($targetUser && $targetUser->getId() == $userId);
        }
        
        if (!$existing || $existing['status'] !== 'pending_key' || $isMsgOverride || $isMsgDeleted) {
            $mazeService->sendMissingKeyHint($userId, $encryptedMsgId, $isMsgOverride, $identityMatch, !!$decrypted);
        }
    } elseif ($publicKey) {
        echo "Found key but no message for user $userId. Hinting...\n";
        if (!$existing || $existing['status'] !== 'pending_message' || $isKeyOverride || $isKeyDeleted) {
            $mazeService->sendMissingMessageHint($userId, $publicKey, $publicKeyMsgId, $isKeyOverride);
        }
    }

    // Update progress
    $mazeProgressRepository->setLastProcessedId($userId, $maxIdInBatch);
}

echo "Maze Processor finished.\n";

<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/MazeChallengeRepository.php';
require_once __DIR__ . '/../services/MazeService.php';

// Initialize DB and Repositories
$database = new Database();
$db = $database->getConnection();

$userRepository = new UserRepository($db);
$messageRepository = new MessageRepository($db);
$mazeChallengeRepository = new MazeChallengeRepository($db);
$mazeService = new MazeService($userRepository, $messageRepository, $mazeChallengeRepository);

echo "Starting Maze Processor...\n";

// 1. Find Admin
$adminUsername = defined('MAZE_ADMIN_USERNAME') ? MAZE_ADMIN_USERNAME : 'admin';
$admin = $userRepository->findByUsername($adminUsername);
if (!$admin) {
    die("Error: Admin user '$adminUsername' not found in database.\n");
}
$adminId = $admin->getId();

// 2. Fetch all unique senders who messaged admin
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

    echo "Checking messages from user ID $userId...\n";

    // Fetch conversation with admin
    $messages = $messageRepository->getConversation($userId, $adminId);
    
    $encryptedUsername = null;
    $publicKey = null;
    $encryptedMsgId = null;
    $publicKeyMsgId = null;

    // Look for PGP message and Public Key
    // We iterate backwards to get the most recent ones
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        $msg = $messages[$i];
        if ($msg->getSenderId() != $userId) continue;

        $content = $msg->getContent();
        
        if (!$encryptedUsername && strpos($content, '-----BEGIN PGP MESSAGE-----') !== false) {
            $encryptedUsername = $content;
            $encryptedMsgId = $msg->getId();
        }
        
        if (!$publicKey && strpos($content, '-----BEGIN PGP PUBLIC KEY BLOCK-----') !== false) {
            $publicKey = $content;
            $publicKeyMsgId = $msg->getId();
        }
        
        if ($encryptedUsername && $publicKey) break;
    }

    if ($encryptedUsername && $publicKey) {
        echo "Found challenge candidate for user $userId. Processing...\n";
        
        // Check if this specific pair was already tried and failed
        if ($existing && $existing['encrypted_username_msg_id'] == $encryptedMsgId && $existing['public_key_msg_id'] == $publicKeyMsgId) {
            echo "Already processed this specific attempt. Skipping.\n";
            continue;
        }

        $result = $mazeService->processChallenge($userId, $encryptedUsername, $publicKey);
        
        if ($result) {
            echo "Successfully processed maze challenge for user $userId.\n";
        } else {
            echo "Failed to process maze challenge for user $userId.\n";
        }
    } else {
        echo "Incomplete challenge data for user $userId (Missing " . (!$encryptedUsername ? "Encrypted Username" : "") . " " . (!$publicKey ? "Public Key" : "") . ").\n";
    }
}

echo "Maze Processor finished.\n";

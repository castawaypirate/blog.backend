<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/MazeChallengeRepository.php';
require_once __DIR__ . '/../repositories/MazeProgressRepository.php';
require_once __DIR__ . '/../services/MazeService.php';
require_once __DIR__ . '/../services/MazeProcessor.php';

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

// Find Admin
$adminUsername = defined('MAZE_ADMIN_USERNAME') ? MAZE_ADMIN_USERNAME : 'admin';
$admin = $userRepository->findByUsername($adminUsername);
if (!$admin) {
    die("Error: Admin user '$adminUsername' not found.\n");
}
$adminId = $admin->getId();

// Delegate to MazeProcessor class
$processor = new MazeProcessor($db, $userRepository, $messageRepository, $mazeChallengeRepository, $mazeProgressRepository, $mazeService);
$results = $processor->processAll($adminId);

foreach ($results as $userId => $action) {
    echo "User $userId: $action\n";
}

echo "Maze Processor finished.\n";

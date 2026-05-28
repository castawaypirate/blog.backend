<?php

/**
 * Bootstrap for Maze Engine PHPUnit tests.
 * Sets up the test database connection and defines constants
 * that MazeService reads at construction time.
 */

// --- 1. Define maze constants (before any class loads) ---
define('MAZE_ADMIN_USERNAME', 'admin');
define('MAZE_PRIVATE_KEY_PATH', __DIR__ . '/fixtures/test-admin-sec.asc');
define('MAZE_PASSPHRASE', 'test_maze_pass');
define('MAZE_BONUS_MESSAGE', 'You cracked the maze! This is the secret bonus message.');

// --- 2. Require class files (no autoloader — matches production pattern) ---
$backendRoot = realpath(__DIR__ . '/..');

require_once $backendRoot . '/vendor/autoload.php';
require_once $backendRoot . '/app/models/User.php';
require_once $backendRoot . '/app/models/Message.php';
require_once $backendRoot . '/app/repositories/UserRepository.php';
require_once $backendRoot . '/app/repositories/MessageRepository.php';
require_once $backendRoot . '/app/repositories/MazeChallengeRepository.php';
require_once $backendRoot . '/app/repositories/MazeProgressRepository.php';
require_once $backendRoot . '/app/services/MazeService.php';
require_once $backendRoot . '/app/services/MazeProcessor.php';

// --- 3. Test database connection ---
function getTestDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=maze_test_db',
            'castaway',
            'pass1234',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

// --- 4. Load the abstract base test class ---
require_once __DIR__ . '/MazeTestCase.php';

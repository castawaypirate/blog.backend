<?php

use PHPUnit\Framework\TestCase;

/**
 * Abstract base class for all Maze Engine integration tests.
 * Handles DB cleanup, user seeding, fixture loading, and provides
 * helper methods for message injection and state queries.
 */
abstract class MazeTestCase extends TestCase
{
    protected static array $payloads;

    protected PDO $db;
    protected UserRepository $userRepo;
    protected MessageRepository $messageRepo;
    protected MazeChallengeRepository $mazeChallengeRepo;
    protected MazeProgressRepository $mazeProgressRepo;
    protected MazeService $mazeService;
    protected MazeProcessor $mazeProcessor;

    protected int $adminId = 1;
    protected int $testUserId = 42;
    protected string $testUsername = 'Alice';

    public static function setUpBeforeClass(): void
    {
        self::$payloads = require __DIR__ . '/fixtures/payloads.php';
    }

    protected function setUp(): void
    {
        $this->db = getTestDb();

        // Truncate all tables (disable FK checks to avoid ordering issues)
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE MazeProgress');
        $this->db->exec('TRUNCATE TABLE MazeChallenges');
        $this->db->exec('TRUNCATE TABLE Messages');
        $this->db->exec('TRUNCATE TABLE Users');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Seed admin user (id: 1)
        $this->db->exec("INSERT INTO Users (id, username, email, password) VALUES (1, 'admin', 'admin@test.com', 'hashed_pw')");

        // Seed test user (id: 42, username: 'Alice')
        $this->db->exec("INSERT INTO Users (id, username, email, password) VALUES (42, 'Alice', 'alice@test.com', 'hashed_pw')");

        // Seed a second user for CT~id scenarios (username 'Bob' must exist for identity mismatch checks)
        $this->db->exec("INSERT INTO Users (id, username, email, password) VALUES (99, 'Bob', 'bob@test.com', 'hashed_pw')");

        // Instantiate repositories
        $this->userRepo = new UserRepository($this->db);
        $this->messageRepo = new MessageRepository($this->db);
        $this->mazeChallengeRepo = new MazeChallengeRepository($this->db);
        $this->mazeProgressRepo = new MazeProgressRepository($this->db);

        // Instantiate services
        $this->mazeService = new MazeService($this->userRepo, $this->messageRepo, $this->mazeChallengeRepo);
        $this->mazeProcessor = new MazeProcessor(
            $this->db,
            $this->userRepo,
            $this->messageRepo,
            $this->mazeChallengeRepo,
            $this->mazeProgressRepo,
            $this->mazeService
        );
    }

    // ---------------------------------------------------------------
    // Helper: Inject a message into the Messages table
    // ---------------------------------------------------------------
    protected function injectMessage(int $senderId, int $receiverId, string $content): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO Messages (sender_id, receiver_id, content) VALUES (:s, :r, :c)"
        );
        $stmt->execute([':s' => $senderId, ':r' => $receiverId, ':c' => $content]);
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Helper: Seed a MazeChallenges row for the test user
    // ---------------------------------------------------------------
    protected function seedChallengeState(
        string $status,
        ?string $publicKey = null,
        ?int $pubKeyMsgId = null,
        ?int $encUsernameMsgId = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO MazeChallenges (user_id, public_key, public_key_msg_id, encrypted_username_msg_id, status)
             VALUES (:uid, :pk, :pkid, :emid, :status)"
        );
        $stmt->execute([
            ':uid' => $this->testUserId,
            ':pk' => $publicKey,
            ':pkid' => $pubKeyMsgId,
            ':emid' => $encUsernameMsgId,
            ':status' => $status,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Helper: Get the latest admin reply to a user
    // ---------------------------------------------------------------
    protected function getAdminReplyTo(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT content FROM Messages
             WHERE sender_id = :admin AND receiver_id = :uid
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':admin' => $this->adminId, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['content'] : null;
    }

    // ---------------------------------------------------------------
    // Helper: Count admin replies to a user
    // ---------------------------------------------------------------
    protected function countAdminReplies(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM Messages WHERE sender_id = :admin AND receiver_id = :uid"
        );
        $stmt->execute([':admin' => $this->adminId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    // ---------------------------------------------------------------
    // Helper: Get the latest MazeChallenges row for the test user
    // ---------------------------------------------------------------
    protected function getLatestChallenge(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM MazeChallenges WHERE user_id = :uid ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ---------------------------------------------------------------
    // Helper: Delete a message (triggers ON DELETE SET NULL cascade)
    // ---------------------------------------------------------------
    protected function deleteMessage(int $messageId): void
    {
        $stmt = $this->db->prepare("DELETE FROM Messages WHERE id = :id");
        $stmt->execute([':id' => $messageId]);
    }

    // ---------------------------------------------------------------
    // Helper: Decrypt an admin reply using the test user's private key
    // ---------------------------------------------------------------
    protected function decryptAdminReply(?string $armoredCiphertext): ?string
    {
        if (empty($armoredCiphertext)) {
            return null;
        }

        // 1. Strip headers and any whitespace/newlines
        $base64 = preg_replace('/-----BEGIN CIPHERTEXT-----|-----END CIPHERTEXT-----|\s+/', '', $armoredCiphertext);
        
        // 2. Decode Base64
        $rawCiphertext = base64_decode($base64, true);
        if ($rawCiphertext === false) {
            return null;
        }

        // 3. Load user private key
        $privKeyPath = __DIR__ . '/fixtures/testuser-sec.asc';
        if (!file_exists($privKeyPath)) {
            $this->fail('Test user private key fixture not found at ' . $privKeyPath);
        }
        
        $privKeyContent = file_get_contents($privKeyPath);
        $privKey = openssl_pkey_get_private($privKeyContent);
        if ($privKey === false) {
            return null;
        }

        // 4. Decrypt using PKCS1_OAEP_PADDING and gracefully suppress warnings
        $decrypted = '';
        if (@openssl_private_decrypt($rawCiphertext, $decrypted, $privKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            return $decrypted;
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Fixture accessors (convenience)
    // ---------------------------------------------------------------
    protected function ctGood(): string   { return self::$payloads['CT_GOOD']; }
    protected function ctWrongId(): string { return self::$payloads['CT_WRONG_ID']; }
    protected function ctBad(): string    { return self::$payloads['CT_BAD']; }
    protected function pkGood(): string   { return self::$payloads['PK_GOOD']; }
    protected function pkBad(): string    { return self::$payloads['PK_BAD']; }
}

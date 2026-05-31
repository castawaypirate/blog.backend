<?php

/**
 * Phase 1: First Contact (No Previous DB State)
 * 
 * Tests scenarios 1.1.1–1.4.6 from the maze scenario matrix.
 * Empty initial MazeChallenges state — all messages are fresh.
 */
class Phase1FirstContactTest extends MazeTestCase
{
    // =================================================================
    // 1.1 — No Maze Content (scenarios 1.1.1–1.1.3)
    // =================================================================

    /** @test 1.1.1 — No messages at all → no reply */
    public function testNoMessagesNoReply(): void
    {
        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    /** @test 1.1.2 — Plaintext message → no reply */
    public function testPlaintextMessageNoReply(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, 'Hello admin, how are you?');

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_maze_content', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    /** @test 1.1.3 — Multiple plaintext messages → no reply */
    public function testMultiplePlaintextMessagesNoReply(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, 'Hello!');
        $this->injectMessage($this->testUserId, $this->adminId, 'Are you there?');
        $this->injectMessage($this->testUserId, $this->adminId, 'Just checking in.');

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_maze_content', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    // =================================================================
    // 1.2 — Ciphertext Only (scenarios 1.2.1–1.2.3)
    // =================================================================

    /** @test 1.2.1 — CT✓ only → missing key hint (identity ok) */
    public function testCTGoodOnlyMissingKeyHint(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 1.2.2 — CT~id only → missing key hint (identity fail) */
    public function testCTWrongIdOnlyMissingKeyHint(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I decrypted your secret, but identity verification failed! Is it really you? AND I still don't have your Public Key to reply securely anyway!",
            $reply
        );
    }

    /** @test 1.2.3 — CT✗ only → missing key hint (not decryptable) */
    public function testCTBadOnlyMissingKeyHint(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I tried to decrypt your message but it's unreadable. Also, I don't have your Public Key yet!",
            $reply
        );
    }

    // =================================================================
    // 1.3 — Public Key Only (scenarios 1.3.1–1.3.2)
    // =================================================================

    /** @test 1.3.1 — PK✓ only → missing message hint */
    public function testPKGoodOnlyMissingMessageHint(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 1.3.2 — PK✗ only → missing message hint (bad key) */
    public function testPKBadOnlyMissingMessageHint(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "That Public Key doesn't look right—generate a new one or make sure you copied it correctly. And I'm still waiting on that encrypted message!",
            $reply
        );
    }

    // =================================================================
    // 1.4 — Both Sent in Same Batch (scenarios 1.4.1–1.4.6)
    // =================================================================

    /** @test 1.4.1 — CT✓ + PK✓ → SUCCESS (encrypted reply) */
    public function testCTGoodPKGoodSuccess(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $decryptedMessage = $this->decryptAdminReply($reply);
        $this->assertSame(
            MAZE_BONUS_MESSAGE,
            $decryptedMessage,
            'The decrypted admin reply did not match the expected bonus message.'
        );

        // Verify DB status is 'replied'
        $challenge = $this->getLatestChallenge($this->testUserId);
        $this->assertSame('replied', $challenge['status']);
    }

    /** @test 1.4.2 — CT✓ + PK✗ → broken key error */
    public function testCTGoodPKBadBrokenKeyError(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your secret message decrypted successfully and verified your identity, but I couldn't encrypt my reply with your Public Key because it looks broken! Generate a new key and try again!",
            $reply
        );
    }

    /** @test 1.4.3 — CT~id + PK✓ → identity failed error */
    public function testCTWrongIdPKGoodIdentityError(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've received your Public Key (it looks good!), but your encrypted message failed identity verification! The decrypted content doesn't match your username. Make sure you encrypt exactly your username!",
            $reply
        );
    }

    /** @test 1.4.4 — CT~id + PK✗ → identity failed + broken key */
    public function testCTWrongIdPKBadBothError(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your Public Key looks broken, and your encrypted message fails identity verification! Fix your encrypted username and generate a new key.",
            $reply
        );
    }

    /** @test 1.4.5 — CT✗ + PK✓ → unreadable message error */
    public function testCTBadPKGoodUnreadableError(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've received your Public Key (it looks good!), but your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    /** @test 1.4.6 — CT✗ + PK✗ → both bad error */
    public function testCTBadPKBadBothBadError(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your encrypted message is unreadable and your Public Key looks broken too. Double-check both on the cryptography site!",
            $reply
        );
    }
}
